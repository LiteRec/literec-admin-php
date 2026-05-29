<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Read;

use App\Households\Application\Query\Port\HouseholdSummary;
use App\Households\Application\Query\Port\MemberAddressDto;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Application\Query\Port\MemberListItem;
use App\Households\Application\Query\Port\MemberProfileDto;
use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MemberResidencyDto;
use App\Households\Application\Query\Port\PageOfMembers;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine DBAL adapter for the {@see MemberReadModel} port. Read side
 * only — uses {@see Connection} (NOT the EntityManager) so we never pay
 * the hydrator/UnitOfWork cost on read paths, and so that projection
 * shapes can evolve independently of the aggregate.
 *
 * Queries hit the same `households` + `household_members` tables that
 * the write side ({@see \App\Households\Infrastructure\Persistence\Doctrine\DoctrineHouseholds})
 * persists into; CQRS-lite.
 *
 * The orgName, gateway, includeMerged, and recentOnly criteria fields
 * are accepted but ignored — the backing columns do not yet exist (see
 * {@see SearchMembersCriteria} for context).
 */
final class DoctrineMemberReadModel implements MemberReadModel
{
    use RowFieldExtraction;

    /**
     * Reused SQL fragments. Extracted for SonarCloud php:S1192.
     */
    private const string SQL_SELECT = 'SELECT ';

    private const string COL_MEMBER_CORE = 'm.id AS member_id, m.household_id, m.code, m.first_name, m.middle_name, ';

    private const string FROM_MEMBERS = 'FROM household_members m ';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function search(SearchMembersCriteria $criteria): PageOfMembers
    {
        [$whereSql, $params, $types] = $this->buildWhere($criteria);

        $countSql = sprintf(
            'SELECT COUNT(*) FROM household_members m INNER JOIN households h ON h.id = m.household_id%s',
            $whereSql,
        );
        $totalRaw = $this->connection->fetchOne($countSql, $params, $types);
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        $offset = ($criteria->page - 1) * $criteria->pageSize;
        $listSql = sprintf(
            self::SQL_SELECT
            . self::COL_MEMBER_CORE
            . 'm.last_name, m.suffix, m.date_of_birth, m.phone, m.residency_status, '
            . 'm.is_primary, m.is_active, '
            . 'h.street, h.city, h.state '
            . self::FROM_MEMBERS
            . 'INNER JOIN households h ON h.id = m.household_id'
            . '%s'
            . ' ORDER BY m.last_name ASC, m.first_name ASC, m.id ASC'
            . ' LIMIT :__limit OFFSET :__offset',
            $whereSql,
        );

        $params['__limit'] = $criteria->pageSize;
        $params['__offset'] = $offset;
        $types['__limit'] = ParameterType::INTEGER;
        $types['__offset'] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative($listSql, $params, $types);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->rowToListItem($row);
        }

        return new PageOfMembers(
            $items,
            $criteria->page,
            $criteria->pageSize,
            $total,
        );
    }

    public function memberDetail(HouseholdId $householdId, MemberId $memberId): MemberDetail
    {
        $sql = self::SQL_SELECT
            . self::COL_MEMBER_CORE
            . 'm.last_name, m.suffix, m.date_of_birth, m.gender, m.email, m.phone, '
            . 'm.residency_status, m.is_primary, m.is_active, '
            . 'm.deactivated_reason, m.deactivated_at, '
            . 'h.name AS household_name, '
            . 'h.street, h.unit, h.city, h.state, h.postal_code, h.country '
            . self::FROM_MEMBERS
            . 'INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE m.household_id = :household_id AND m.id = :member_id';

        $row = $this->connection->fetchAssociative($sql, [
            'household_id' => $householdId->value,
            'member_id'    => $memberId->value,
        ]);

        if ($row === false) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        $summary = $this->loadHouseholdSummary($householdId, $this->rowString($row, 'household_name'));
        $householdMembers = $this->loadHouseholdMembers($householdId);

        return new MemberDetail(
            $summary,
            $this->rowToProfile($row),
            $this->rowToAddress($row),
            new MemberResidencyDto(
                $this->rowString($row, 'residency_status'),
                $this->loadLatestResidencyEffectiveFrom($memberId),
            ),
            $householdMembers,
        );
    }

    /**
     * Looks up the effective-from date of the most recent
     * `household_residency_history` row for the member, or null when no
     * row exists. The history table is the only persistent record of
     * residency changes; the column on `household_members` only carries
     * the current status, not when it took effect.
     */
    private function loadLatestResidencyEffectiveFrom(MemberId $memberId): ?string
    {
        $sql = 'SELECT effective_from FROM household_residency_history '
            . 'WHERE member_id = :member_id '
            . 'ORDER BY effective_from DESC, id DESC LIMIT 1';

        $value = $this->connection->fetchOne($sql, ['member_id' => $memberId->value]);

        if ($value === false || $value === null) {
            return null;
        }

        return $this->normalizeDate($value);
    }

    /**
     * Loads every member of the household — including deactivated ones —
     * for the Household card roster (LRA-42). Sorted to match the list page
     * (lastName / firstName / id ASC).
     *
     * This is the third SELECT issued by {@see memberDetail()} (member row
     * + summary aggregate + this list). The card needs the full roster
     * regardless of `is_active` so staff can see who is currently
     * deactivated; the row template dims those entries client-side.
     *
     * @return list<MemberListItem>
     */
    private function loadHouseholdMembers(HouseholdId $householdId): array
    {
        $sql = self::SQL_SELECT
            . self::COL_MEMBER_CORE
            . 'm.last_name, m.suffix, m.date_of_birth, m.phone, m.residency_status, '
            . 'm.is_primary, m.is_active, '
            . 'h.street, h.city, h.state '
            . self::FROM_MEMBERS
            . 'INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE m.household_id = :household_id '
            . 'ORDER BY m.last_name ASC, m.first_name ASC, m.id ASC';

        $rows = $this->connection->fetchAllAssociative($sql, [
            'household_id' => $householdId->value,
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->rowToListItem($row);
        }

        return $items;
    }

    /**
     * @return array{
     *     0: string,
     *     1: array<string, mixed>,
     *     2: array<string, ArrayParameterType|ParameterType|Type|string>,
     * }
     */
    private function buildWhere(SearchMembersCriteria $c): array
    {
        $clauses = [];
        /** @var array<string, mixed> $params */
        $params = [];
        /** @var array<string, ArrayParameterType|ParameterType|Type|string> $types */
        $types = [];

        if (!$c->includeDeleted) {
            $clauses[] = 'm.is_active = :is_active';
            $params['is_active'] = true;
            $types['is_active'] = ParameterType::BOOLEAN;
        }

        if ($c->primaryOnly) {
            $clauses[] = 'm.is_primary = :is_primary';
            $params['is_primary'] = true;
            $types['is_primary'] = ParameterType::BOOLEAN;
        }

        if ($c->memberCode !== null) {
            $clauses[] = 'm.code = :member_code';
            $params['member_code'] = $c->memberCode;
        }

        if ($c->lastName !== null) {
            $clauses[] = 'LOWER(m.last_name) LIKE :last_name';
            $params['last_name'] = '%' . strtolower($c->lastName) . '%';
        }

        if ($c->firstName !== null) {
            $clauses[] = 'LOWER(m.first_name) LIKE :first_name';
            $params['first_name'] = '%' . strtolower($c->firstName) . '%';
        }

        if ($c->email !== null) {
            $clauses[] = 'LOWER(COALESCE(m.email, \'\')) LIKE :email';
            $params['email'] = '%' . strtolower($c->email) . '%';
        }

        if ($c->phone !== null) {
            $clauses[] = 'LOWER(COALESCE(m.phone, \'\')) LIKE :phone';
            $params['phone'] = '%' . strtolower($c->phone) . '%';
        }

        // receipt, orgName, gateway, includeMerged, recentOnly:
        // no backing columns yet — see SearchMembersCriteria docblock.

        $whereSql = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        return [$whereSql, $params, $types];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToListItem(array $row): MemberListItem
    {
        $addressShort = sprintf(
            '%s, %s %s',
            $this->rowString($row, 'street'),
            $this->rowString($row, 'city'),
            $this->rowString($row, 'state'),
        );

        return new MemberListItem(
            $this->rowString($row, 'member_id'),
            $this->rowString($row, 'household_id'),
            $this->rowString($row, 'code'),
            $this->joinName(
                $this->rowString($row, 'first_name'),
                $this->rowNullableString($row, 'middle_name'),
                $this->rowString($row, 'last_name'),
                $this->rowNullableString($row, 'suffix'),
            ),
            $this->normalizeDate($row['date_of_birth'] ?? null),
            $this->rowNullableString($row, 'phone'),
            $addressShort,
            $this->rowString($row, 'residency_status'),
            $this->rowBool($row, 'is_primary'),
            $this->rowBool($row, 'is_active'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToProfile(array $row): MemberProfileDto
    {
        $firstName  = $this->rowString($row, 'first_name');
        $middleName = $this->rowNullableString($row, 'middle_name');
        $lastName   = $this->rowString($row, 'last_name');
        $suffix     = $this->rowNullableString($row, 'suffix');

        return new MemberProfileDto(
            $this->rowString($row, 'member_id'),
            $this->rowString($row, 'code'),
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $this->joinName($firstName, $middleName, $lastName, $suffix),
            $this->normalizeDate($row['date_of_birth'] ?? null),
            $this->rowString($row, 'gender'),
            $this->rowNullableString($row, 'email'),
            $this->rowNullableString($row, 'phone'),
            $this->rowBool($row, 'is_primary'),
            $this->rowBool($row, 'is_active'),
            $this->rowNullableString($row, 'deactivated_reason'),
            $this->normalizeDateTime($row['deactivated_at'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToAddress(array $row): MemberAddressDto
    {
        return new MemberAddressDto(
            $this->rowString($row, 'street'),
            $this->rowNullableString($row, 'unit'),
            $this->rowString($row, 'city'),
            $this->rowString($row, 'state'),
            $this->rowString($row, 'postal_code'),
            $this->rowString($row, 'country'),
        );
    }

    private function loadHouseholdSummary(HouseholdId $householdId, string $householdName): HouseholdSummary
    {
        $sql = self::SQL_SELECT
            . 'COUNT(*) AS member_count, '
            . 'MAX(CASE WHEN is_primary THEN id END) AS primary_id, '
            . 'MAX(CASE WHEN is_primary THEN first_name END) AS primary_first, '
            . 'MAX(CASE WHEN is_primary THEN middle_name END) AS primary_middle, '
            . 'MAX(CASE WHEN is_primary THEN last_name END) AS primary_last, '
            . 'MAX(CASE WHEN is_primary THEN suffix END) AS primary_suffix '
            . 'FROM household_members WHERE household_id = :household_id';

        $row = $this->connection->fetchAssociative($sql, [
            'household_id' => $householdId->value,
        ]);

        if ($row === false) {
            return new HouseholdSummary($householdId->value, $householdName, 0, '', '');
        }

        $memberCount = is_numeric($row['member_count'] ?? null) ? (int) $row['member_count'] : 0;
        $primaryId = $this->rowNullableString($row, 'primary_id') ?? '';

        $primaryFirst = $this->rowNullableString($row, 'primary_first');
        $primaryLast = $this->rowNullableString($row, 'primary_last');
        $primaryMiddle = $this->rowNullableString($row, 'primary_middle');
        $primarySuffix = $this->rowNullableString($row, 'primary_suffix');

        $primaryFullName = '';
        if ($primaryFirst !== null && $primaryLast !== null) {
            $primaryFullName = $this->joinName($primaryFirst, $primaryMiddle, $primaryLast, $primarySuffix);
        }

        return new HouseholdSummary(
            $householdId->value,
            $householdName,
            $memberCount,
            $primaryId,
            $primaryFullName,
        );
    }

    private function joinName(string $first, ?string $middle, string $last, ?string $suffix): string
    {
        $parts = array_filter(
            [$first, $middle, $last, $suffix],
            static fn(?string $p): bool => $p !== null && $p !== '',
        );

        return implode(' ', $parts);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Postgres DATE comes back as 'YYYY-MM-DD'; trim any time suffix
        // defensively in case the driver hydrates a 'YYYY-MM-DD 00:00:00'.
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $parsed === false ? null : $parsed->format('Y-m-d');
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed->format(\DateTimeInterface::ATOM);
    }
}
