<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Infrastructure\Authorization;

use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\Privilege;
use App\Administration\Infrastructure\Authorization\CataloguePrivilegeLookup;
use App\Tests\Support\Fake\RecordingLogger;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class CataloguePrivilegeLookupTest extends TestCase
{
    #[Test]
    #[TestDox('A known name resolves to its Privilege case.')]
    public function known_name_resolves_to_its_case(): void
    {
        $lookup = new CataloguePrivilegeLookup(new RecordingLogger());

        self::assertSame(Privilege::ViewAdmins, $lookup->privilegeNamed('VIEW_ADMINS'));
    }

    #[Test]
    #[TestDox('An unknown name throws UnknownPrivilege and records one warning naming the rejected privilege.')]
    public function unknown_name_throws_and_logs_a_warning(): void
    {
        $logger = new RecordingLogger();
        $lookup = new CataloguePrivilegeLookup($logger);

        try {
            $lookup->privilegeNamed('NOT_A_REAL_PRIVILEGE');
            self::fail('Expected UnknownPrivilege to be thrown.');
        } catch (UnknownPrivilege) {
            // expected
        }

        $records = $logger->records();
        self::assertCount(1, $records);
        self::assertSame('warning', $records[0]['level']);
        self::assertSame('NOT_A_REAL_PRIVILEGE', $records[0]['context']['privilege_name']);
    }
}
