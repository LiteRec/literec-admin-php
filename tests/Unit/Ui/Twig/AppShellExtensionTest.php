<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Twig;

use App\Ui\Twig\AppShellExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Pins the avatar-initials helper used by the app-shell header: up to two
 * uppercase initials derived from a display name or username, split on
 * whitespace and the common username separators.
 *
 * Test methods use the project-standard snake_case behavioural names (CLAUDE.md);
 * a PHPMD CamelCaseMethodName suppression is intentionally NOT added because
 * PHPStan's strict phpDoc parser rejects that annotation and PHPMD is not part
 * of this repo's toolchain.
 */
#[Small]
final class AppShellExtensionTest extends TestCase
{
    private function extension(): AppShellExtension
    {
        return new AppShellExtension('Main Facility', 'dev');
    }

    #[Test]
    #[DataProvider('initialsCases')]
    #[TestDox('user_initials derives up to two uppercase initials.')]
    public function it_derives_avatar_initials(string $name, string $expected): void
    {
        self::assertSame($expected, $this->extension()->userInitials($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function initialsCases(): iterable
    {
        yield 'two words' => ['Leslie Knope', 'LK'];
        yield 'dot separated' => ['leslie.knope', 'LK'];
        yield 'underscore separated' => ['shell_e2e', 'SE'];
        yield 'hyphen separated' => ['first-last', 'FL'];
        yield 'single token' => ['lknope', 'L'];
        yield 'three words capped at two' => ['a b c', 'AB'];
        yield 'surrounding whitespace' => ['  spaced  name  ', 'SN'];
        yield 'multibyte' => ['élan vital', 'ÉV'];
        yield 'empty' => ['', ''];
        yield 'blank' => ['   ', ''];
    }

    #[Test]
    #[TestDox('The selected-facility and build-version helpers return their injected values.')]
    public function it_exposes_the_injected_shell_values(): void
    {
        $extension = new AppShellExtension('Pawnee Community Center', '2.4.0');

        self::assertSame('Pawnee Community Center', $extension->selectedFacility());
        self::assertSame('2.4.0', $extension->buildVersion());
    }
}
