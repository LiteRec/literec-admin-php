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
            'SELECT '
            . 'm.id AS member_id, m.household_id, m.code, m.first_name, m.middle_name, '
            . 'm.last_name, m.suffix, m.date_of_birth, m.phone, m.residency_status, '
            . 'm.is_primary, m.is_active, '
            . 'h.street, h.city, h.state '
            . 'FROM household_members m '
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
        $sql = 'SELECT '
            . 'm.id AS member_id, m.household_id, m.code, m.first_name, m.middle_name, '
            . 'm.last_name, m.suffix, m.date_of_birth, m.gender, m.email, m.phone, '
            . 'm.residency_status, m.is_primary, m.is_active, '
            . 'm.deactivated_reason, m.deactivated_at, '
            . 'h.name AS household_name, '
            . 'h.street, h.unit, h.city, h.state, h.postal_code, h.country '
            . 'FROM household_members m '
            . 'INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE m.household_id = :household_id AND m.id = :member_id';

        $row = $this->connection->fetchAssociative($sql, [
            'household_id' => $householdId->value,
            'member_id'    => $memberId->value,
        ]);

        if ($row === false) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        $summary = $this->loadHouseholdSummary($householdId, $this->str($row, 'household_name'));

        return new MemberDetail(
            $summary,
            $this->rowToProfile($row),
            $this->rowToAddress($row),
            new MemberResidencyDto($this->str($row, 'residency_status')),
        );
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
            $this->str($row, 'street'),
            $this->str($row, 'city'),
            $this->str($row, 'state'),
        );

        return new MemberListItem(
            $this->str($row, 'member_id'),
            $this->str($row, 'household_id'),
            $this->str($row, 'code'),
            $this->joinName(
                $this->str($row, 'first_name'),
                $this->nullableStr($row, 'middle_name'),
                $this->str($row, 'last_name'),
                $this->nullableStr($row, 'suffix'),
            ),
            $this->normalizeDate($row['date_of_birth'] ?? null),
            $this->nullableStr($row, 'phone'),
            $addressShort,
            $this->str($row, 'residency_status'),
            $this->bool($row, 'is_primary'),
            $this->bool($row, 'is_active'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToProfile(array $row): MemberProfileDto
    {
        $firstName  = $this->str($row, 'first_name');
        $middleName = $this->nullableStr($row, 'middle_name');
        $lastName   = $this->str($row, 'last_name');
        $suffix     = $this->nullableStr($row, 'suffix');

        return new MemberProfileDto(
            $this->str($row, 'member_id'),
            $this->str($row, 'code'),
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $this->joinName($firstName, $middleName, $lastName, $suffix),
            $this->normalizeDate($row['date_of_birth'] ?? null),
            $this->str($row, 'gender'),
            $this->nullableStr($row, 'email'),
            $this->nullableStr($row, 'phone'),
            $this->bool($row, 'is_primary'),
            $this->bool($row, 'is_active'),
            $this->nullableStr($row, 'deactivated_reason'),
            $this->normalizeDateTime($row['deactivated_at'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToAddress(array $row): MemberAddressDto
    {
        return new MemberAddressDto(
            $this->str($row, 'street'),
            $this->nullableStr($row, 'unit'),
            $this->str($row, 'city'),
            $this->str($row, 'state'),
            $this->str($row, 'postal_code'),
            $this->str($row, 'country'),
        );
    }

    private function loadHouseholdSummary(HouseholdId $householdId, string $householdName): HouseholdSummary
    {
        $sql = 'SELECT '
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
        $primaryId = $this->nullableStr($row, 'primary_id') ?? '';

        $primaryFirst = $this->nullableStr($row, 'primary_first');
        $primaryLast = $this->nullableStr($row, 'primary_last');
        $primaryMiddle = $this->nullableStr($row, 'primary_middle');
        $primarySuffix = $this->nullableStr($row, 'primary_suffix');

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

    /**
     * @param array<string, mixed> $row
     */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableStr(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0' && strtolower($value) !== 'f' && strtolower($value) !== 'false';
        }

        return false;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!is_string($value)) {
            return null;
        }
        if ($value === '') {
            return null;
        }

        // Postgres DATE comes back as 'YYYY-MM-DD'; trim any time suffix
        // defensively in case the driver hydrates a 'YYYY-MM-DD 00:00:00'.
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $parsed === false ? null : $parsed->format('Y-m-d');
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (!is_string($value)) {
            return null;
        }
        if ($value === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed->format(\DateTimeInterface::ATOM);
    }
}
