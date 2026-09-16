<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\MemberContact;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberContactTest extends TestCase
{
    #[Test]
    #[TestDox('::of() exposes the supplied email and phone as public properties.')]
    public function of_exposes_email_and_phone(): void
    {
        $email = EmailAddress::of('alice@example.com');
        $phone = PhoneNumber::of('5550001');

        $contact = MemberContact::of($email, $phone);

        self::assertNotNull($contact->email);
        self::assertTrue($contact->email->equals($email));
        self::assertNotNull($contact->phone);
        self::assertTrue($contact->phone->equals($phone));
    }

    #[Test]
    #[TestDox('::none() produces a contact with both channels null.')]
    public function none_produces_a_blank_contact(): void
    {
        $contact = MemberContact::none();

        self::assertNull($contact->email);
        self::assertNull($contact->phone);
    }

    #[Test]
    #[TestDox('::filledFrom() keeps the existing email/phone when both are already populated.')]
    public function filled_from_keeps_existing_values_when_populated(): void
    {
        $existingEmail = EmailAddress::of('existing@example.com');
        $existingPhone = PhoneNumber::of('5551111');
        $contact = MemberContact::of($existingEmail, $existingPhone);

        $filled = $contact->filledFrom(EmailAddress::of('found@example.com'), PhoneNumber::of('5559999'));

        self::assertNotNull($filled->email);
        self::assertTrue($filled->email->equals($existingEmail));
        self::assertNotNull($filled->phone);
        self::assertTrue($filled->phone->equals($existingPhone));
    }

    #[Test]
    #[TestDox('::filledFrom() takes the supplied email/phone only where the existing value is blank.')]
    public function filled_from_fills_only_blank_fields(): void
    {
        $contact = MemberContact::none();

        $filled = $contact->filledFrom(EmailAddress::of('found@example.com'), PhoneNumber::of('5559999'));

        self::assertNotNull($filled->email);
        self::assertTrue($filled->email->equals(EmailAddress::of('found@example.com')));
        self::assertNotNull($filled->phone);
        self::assertTrue($filled->phone->equals(PhoneNumber::of('5559999')));
    }

    #[Test]
    #[TestDox('::filledFrom() leaves a blank field blank when nothing is supplied for it.')]
    public function filled_from_leaves_blank_when_nothing_supplied(): void
    {
        $contact = MemberContact::none();

        $filled = $contact->filledFrom(null, null);

        self::assertNull($filled->email);
        self::assertNull($filled->phone);
    }

    #[Test]
    #[TestDox('::equals() is true when both contacts hold the same email and phone.')]
    public function equals_true_for_same_values(): void
    {
        $a = MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001'));
        $b = MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001'));

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is true for two blank contacts.')]
    public function equals_true_for_two_blank_contacts(): void
    {
        self::assertTrue(MemberContact::none()->equals(MemberContact::none()));
    }

    #[Test]
    #[TestDox('::equals() is false when the email differs, including one side being null.')]
    public function equals_false_for_different_email(): void
    {
        $a = MemberContact::of(EmailAddress::of('alice@example.com'), null);
        $b = MemberContact::of(EmailAddress::of('other@example.com'), null);
        $c = MemberContact::none();

        self::assertFalse($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($c->equals($a));
    }

    #[Test]
    #[TestDox('::equals() is false when the phone differs, including one side being null.')]
    public function equals_false_for_different_phone(): void
    {
        $a = MemberContact::of(null, PhoneNumber::of('5550001'));
        $b = MemberContact::of(null, PhoneNumber::of('5550002'));
        $c = MemberContact::none();

        self::assertFalse($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($c->equals($a));
    }
}
