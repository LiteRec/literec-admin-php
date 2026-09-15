<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\Doctrine\Type\MemberIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * The platform argument is unused by {@see MemberIdType}'s conversion
 * methods; a real {@see PostgreSQLPlatform} instance is passed simply
 * because the interface requires one.
 */
#[Small]
final class MemberIdTypeTest extends TestCase
{
    private const string VALID_ID = '019571bf-5d54-7000-b500-000000000e01';

    private MemberIdType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new MemberIdType();
        $this->platform = new PostgreSQLPlatform();
    }

    #[Test]
    #[TestDox('convertToDatabaseValue() returns the scalar value of a MemberId instance.')]
    public function converts_member_id_instance_to_scalar(): void
    {
        $value = $this->type->convertToDatabaseValue(MemberId::fromString(self::VALID_ID), $this->platform);

        self::assertSame(self::VALID_ID, $value);
    }

    #[Test]
    #[TestDox('convertToDatabaseValue() accepts an already-converted, well-formed string (LRA-210 derived identity).')]
    public function converts_well_formed_string_to_itself(): void
    {
        $value = $this->type->convertToDatabaseValue(self::VALID_ID, $this->platform);

        self::assertSame(self::VALID_ID, $value);
    }

    #[Test]
    #[TestDox('convertToDatabaseValue() rejects a malformed string with InvalidMemberId, not passing it through.')]
    public function rejects_malformed_string(): void
    {
        $this->expectException(InvalidMemberId::class);

        $this->type->convertToDatabaseValue('not-a-uuid', $this->platform);
    }

    #[Test]
    #[TestDox('convertToDatabaseValue() returns null for null.')]
    public function converts_null_to_null(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[Test]
    #[TestDox('convertToPHPValue() parses a well-formed string into a MemberId.')]
    public function converts_string_to_member_id(): void
    {
        $value = $this->type->convertToPHPValue(self::VALID_ID, $this->platform);

        self::assertNotNull($value);
        self::assertTrue($value->equals(MemberId::fromString(self::VALID_ID)));
    }
}
