<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Read;

use App\Households\Application\Query\Port\HouseholdSummary;
use App\Households\Application\Query\Port\LinkedHouseholdDto;
use App\Households\Application\Query\Port\MemberAddressDto;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Application\Query\Port\MemberListItem;
use App\Households\Application\Query\Port\MemberProfileDto;
use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MemberResidencyDto;
use App\Households\Application\Query\Port\MemberSegmentCounts;
use App\Households\Application\Query\Port\MembersSegment;
use App\Households\Application\Query\Port\PageOfMembers;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ResidencyStatus;
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
 * The orgName, gateway, and recentOnly criteria fields are accepted but
 * ignored — the backing columns do not yet exist (see
 * {@see SearchMembersCriteria} for context). includeMerged (LRA-208) is
 * honoured via {@see self::buildWhere()} and {@see self::segmentCounts()}.
 */
final class DoctrineMemberReadModel implements MemberReadModel
{
    use RowFieldExtraction;

    /**
     * Reused SQL fragments. Extracted for SonarCloud php:S1192.
     */
    private const string SQL_SELECT = 'SELECT ';

    private const string COL_MEMBER_CORE = 'm.id AS member_id, m.household_id, m.code, m.first_name, m.middle_name, ';

    private const string COL_LIST_ITEM_EXTRA = 'm.email, h.name AS household_name, ';

    private const string COL_PHOTO = 'm.photo_storage_key, ';

    private const string COL_MERGED = 'm.merged_into_member_id, ';

    private const string COL_ANONYMIZED = 'm.anonymized_at, ';

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
            . self::COL_LIST_ITEM_EXTRA
            . self::COL_PHOTO
            . self::COL_MERGED
            . self::COL_ANONYMIZED
            . 'm.last_name, m.suffix, m.date_of_birth, m.phone, m.residency_status, '
            . 'm.is_primary, m.is_active, '
            . 'h.street, h.city, h.state, FALSE AS is_shared '
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

    /**
     * Matches $memberId whose home household is $householdId (unchanged
     * behaviour) OR whose home household has shared $memberId with
     * $householdId (LRA-210). `h` joins the VIEWED household ($householdId)
     * rather than the member's own household_id, so the Address card and
     * `household` summary always reflect the household being browsed;
     * `home_h` separately carries the home household's id/name for
     * {@see MemberDetail::$homeHouseholdId} and the home entry in
     * {@see MemberDetail::$linkedHouseholds} — identity edits always route
     * through the home household regardless of which household is viewed.
     */
    public function memberDetail(HouseholdId $householdId, MemberId $memberId): MemberDetail
    {
        $sql = self::SQL_SELECT
            . self::COL_MEMBER_CORE
            . 'm.last_name, m.suffix, m.nickname, m.date_of_birth, m.gender, m.email, m.phone, '
            . 'm.salutation, m.height_inches, m.weight_pounds, '
            . 'm.residency_status, m.is_primary, m.is_active, '
            . 'm.deactivated_reason, m.deactivated_at, m.anonymized_at, '
            . 'm.photo_storage_key, m.photo_format, '
            . 'm.merged_into_member_id, m.merged_at, s.household_id AS merged_into_household_id, '
            . 'h.name AS household_name, '
            . 'h.street, h.unit, h.city, h.state, h.postal_code, h.country, '
            . 'home_h.name AS home_household_name '
            . self::FROM_MEMBERS
            . 'INNER JOIN households h ON h.id = :household_id '
            . 'INNER JOIN households home_h ON home_h.id = m.household_id '
            . 'LEFT JOIN household_members s ON s.id = m.merged_into_member_id '
            . 'WHERE m.id = :member_id '
            . 'AND (m.household_id = :household_id OR EXISTS ('
            . 'SELECT 1 FROM household_member_affiliations aff '
            . 'WHERE aff.member_id = m.id AND aff.household_id = :household_id'
            . '))';

        $row = $this->connection->fetchAssociative($sql, [
            'household_id' => $householdId->value,
            'member_id'    => $memberId->value,
        ]);

        if ($row === false) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        $summary = $this->loadHouseholdSummary($householdId, $this->rowString($row, 'household_name'));
        $householdMembers = $this->loadHouseholdMembers($householdId);
        $homeHouseholdId = $this->rowString($row, 'household_id');
        $linkedHouseholds = $this->loadLinkedHouseholds(
            $memberId,
            $homeHouseholdId,
            $this->rowString($row, 'home_household_name'),
        );

        return new MemberDetail(
            $summary,
            $this->rowToProfile($row),
            $this->rowToAddress($row),
            new MemberResidencyDto(
                $this->rowString($row, 'residency_status'),
                $this->loadLatestResidencyEffectiveFrom($memberId),
            ),
            $householdMembers,
            $homeHouseholdId,
            $linkedHouseholds,
        );
    }

    /**
     * @return list<LinkedHouseholdDto>
     */
    private function loadLinkedHouseholds(MemberId $memberId, string $homeHouseholdId, string $homeHouseholdName): array
    {
        $items = [new LinkedHouseholdDto($homeHouseholdId, $homeHouseholdName, true, null)];

        $sql = 'SELECT h2.id AS household_id, h2.name AS household_name, aff.linked_at '
            . 'FROM household_member_affiliations aff '
            . 'INNER JOIN households h2 ON h2.id = aff.household_id '
            . 'WHERE aff.member_id = :member_id '
            . 'ORDER BY aff.linked_at ASC';

        $rows = $this->connection->fetchAllAssociative($sql, ['member_id' => $memberId->value]);
        foreach ($rows as $row) {
            $items[] = new LinkedHouseholdDto(
                $this->rowString($row, 'household_id'),
                $this->rowString($row, 'household_name'),
                false,
                $this->normalizeDateTime($row['linked_at'] ?? null),
            );
        }

        return $items;
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
     * for the Household card roster (LRA-42). UNIONs the household's own
     * (home) members with members whose home household has shared them
     * with this household (LRA-210); `is_shared` distinguishes the two so
     * the roster row can render the "Shared" badge. Sorted to match the
     * list page (lastName / firstName / id ASC).
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
        $columns = self::COL_MEMBER_CORE
            . self::COL_LIST_ITEM_EXTRA
            . self::COL_PHOTO
            . self::COL_MERGED
            . self::COL_ANONYMIZED
            . 'm.last_name, m.suffix, m.date_of_birth, m.phone, m.residency_status, '
            . 'm.is_primary, m.is_active, '
            . 'h.street, h.city, h.state';

        $sql = '(' . self::SQL_SELECT . $columns . ', FALSE AS is_shared '
            . self::FROM_MEMBERS
            . 'INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE m.household_id = :household_id) '
            . 'UNION ALL '
            . '(' . self::SQL_SELECT . $columns . ', TRUE AS is_shared '
            . 'FROM household_member_affiliations aff '
            . 'INNER JOIN household_members m ON m.id = aff.member_id '
            . 'INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE aff.household_id = :household_id) '
            . 'ORDER BY last_name ASC, first_name ASC, member_id ASC';

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

        if ($c->segment === MembersSegment::Inactive) {
            $clauses[] = 'm.is_active = :is_active';
            $params['is_active'] = false;
            $types['is_active'] = ParameterType::BOOLEAN;
        } elseif (!$c->includeDeleted) {
            $clauses[] = 'm.is_active = :is_active';
            $params['is_active'] = true;
            $types['is_active'] = ParameterType::BOOLEAN;
        }

        if (!$c->includeMerged) {
            $clauses[] = 'm.merged_into_member_id IS NULL';
        }

        if ($c->segment === MembersSegment::Residents) {
            $clauses[] = 'm.residency_status = :segment_residency';
            $params['segment_residency'] = ResidencyStatus::Resident->value;
        } elseif ($c->segment === MembersSegment::NonResidents) {
            $clauses[] = 'm.residency_status = :segment_residency';
            $params['segment_residency'] = ResidencyStatus::NonResident->value;
        }

        if ($c->q !== null) {
            $clauses[] = $this->qClause('q');
            $params['q'] = self::likeTerm($c->q);
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

        // receipt, orgName, gateway, recentOnly:
        // no backing columns yet — see SearchMembersCriteria docblock.

        $whereSql = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        return [$whereSql, $params, $types];
    }

    /**
     * The Users list free-text search (LRA-192): matches last name, first
     * name, member code, household name, or phone. Deliberately excludes
     * email — the detailed "More filters" field covers that.
     */
    private function qClause(string $paramName): string
    {
        return sprintf(
            '(LOWER(m.last_name) LIKE :%1$s OR LOWER(m.first_name) LIKE :%1$s '
            . 'OR LOWER(m.code) LIKE :%1$s OR LOWER(h.name) LIKE :%1$s '
            . 'OR LOWER(COALESCE(m.phone, \'\')) LIKE :%1$s)',
            $paramName,
        );
    }

    /**
     * Escapes LIKE metacharacters (`%`, `_`, and a literal backslash) in
     * user-supplied search text before wrapping it in wildcards, so a term
     * like `_` or `%` cannot widen the match to "everything" — Postgres'
     * default LIKE ESCAPE character is backslash, so no ESCAPE clause is
     * needed. Uses mb_strtolower() (not strtolower(), which is byte-wise)
     * so non-ASCII case folding (e.g. "Ü" vs "ü") matches Postgres LOWER().
     */
    private static function likeTerm(string $value): string
    {
        return '%' . addcslashes(mb_strtolower($value), '\\%_') . '%';
    }

    public function segmentCounts(?string $q): MemberSegmentCounts
    {
        $qClause = '';
        $params = [];
        if ($q !== null) {
            $qClause = ' AND ' . $this->qClause('q');
            $params['q'] = self::likeTerm($q);
        }

        $sql = sprintf(
            'SELECT '
            . 'SUM(CASE WHEN m.is_active THEN 1 ELSE 0 END) AS all_count, '
            . 'SUM(CASE WHEN m.is_active AND m.residency_status = :resident '
            . 'THEN 1 ELSE 0 END) AS residents_count, '
            . 'SUM(CASE WHEN m.is_active AND m.residency_status = :non_resident '
            . 'THEN 1 ELSE 0 END) AS non_residents_count, '
            . 'SUM(CASE WHEN NOT m.is_active THEN 1 ELSE 0 END) AS inactive_count '
            . 'FROM household_members m INNER JOIN households h ON h.id = m.household_id '
            . 'WHERE m.merged_into_member_id IS NULL%s',
            $qClause,
        );

        $params['resident'] = ResidencyStatus::Resident->value;
        $params['non_resident'] = ResidencyStatus::NonResident->value;

        $row = $this->connection->fetchAssociative($sql, $params);

        if ($row === false) {
            return new MemberSegmentCounts(0, 0, 0, 0);
        }

        return new MemberSegmentCounts(
            $this->rowInt($row, 'all_count'),
            $this->rowInt($row, 'residents_count'),
            $this->rowInt($row, 'non_residents_count'),
            $this->rowInt($row, 'inactive_count'),
        );
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
            $this->rowString($row, 'household_name'),
            $this->rowString($row, 'code'),
            $this->joinName(
                $this->rowString($row, 'first_name'),
                $this->rowNullableString($row, 'middle_name'),
                $this->rowString($row, 'last_name'),
                $this->rowNullableString($row, 'suffix'),
            ),
            $this->rowNullableString($row, 'email'),
            $this->normalizeDate($row['date_of_birth'] ?? null),
            $this->rowNullableString($row, 'phone'),
            $addressShort,
            $this->rowString($row, 'residency_status'),
            $this->rowBool($row, 'is_primary'),
            $this->rowBool($row, 'is_active'),
            $this->photoVersion($this->rowNullableString($row, 'photo_storage_key')),
            $this->rowNullableString($row, 'merged_into_member_id') !== null,
            $this->rowBool($row, 'is_shared'),
            $this->rowNullableString($row, 'anonymized_at') !== null,
        );
    }

    /**
     * The persisted photo storage key's basename without its extension,
     * mirroring {@see \App\Households\Domain\ValueObject\ProfilePhoto::version()}.
     * Computed directly from the row rather than constructing the domain
     * value object: this read-side adapter deliberately never re-hydrates
     * domain types (CQRS-lite), and the key alone is sufficient to derive
     * the cache-busting version segment.
     */
    private function photoVersion(?string $storageKey): ?string
    {
        if ($storageKey === null) {
            return null;
        }

        $basename = basename($storageKey);
        $dot = strrpos($basename, '.');

        return $dot === false ? $basename : substr($basename, 0, $dot);
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
            $this->rowNullableString($row, 'nickname'),
            $this->rowNullableString($row, 'salutation'),
            $this->rowNullableInt($row, 'height_inches'),
            $this->rowNullableInt($row, 'weight_pounds'),
            $this->rowBool($row, 'is_primary'),
            $this->rowBool($row, 'is_active'),
            $this->rowNullableString($row, 'deactivated_reason'),
            $this->normalizeDateTime($row['deactivated_at'] ?? null),
            $this->photoVersion($this->rowNullableString($row, 'photo_storage_key')),
            $this->rowNullableString($row, 'photo_format'),
            $this->rowNullableString($row, 'merged_into_member_id'),
            $this->rowNullableString($row, 'merged_into_household_id'),
            $this->normalizeDateTime($row['merged_at'] ?? null),
            $this->normalizeDateTime($row['anonymized_at'] ?? null),
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
            . '(SELECT COUNT(*) FROM household_members WHERE household_id = :household_id) '
            . '+ (SELECT COUNT(*) FROM household_member_affiliations WHERE household_id = :household_id) '
            . 'AS member_count, '
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
