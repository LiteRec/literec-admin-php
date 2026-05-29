<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Pins the contract of the components/_icon.html.twig macro: it renders an
 * inline, decorative SVG that inherits color via currentColor, honours the
 * size/stroke/class arguments, exposes every documented icon name, and fails
 * quietly (empty svg, no error) for an unknown name.
 *
 * The macro uses no Symfony-specific Twig functions, so it is exercised with a
 * standalone Twig environment rooted at templates/ — a fast #[Small] unit test
 * with no container or database.
 */
#[Small]
final class IconComponentTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 3) . '/templates');
        $this->twig = new Environment($loader);
    }

    private function renderIcon(string $call): string
    {
        return trim($this->twig
            ->createTemplate("{% import 'components/_icon.html.twig' as icon %}{$call}")
            ->render());
    }

    /**
     * Renders by binding the name as a Twig variable rather than interpolating
     * it into the template source, so an arbitrary name can never break parsing.
     */
    private function renderNamed(string $name): string
    {
        return trim($this->twig
            ->createTemplate("{% import 'components/_icon.html.twig' as icon %}{{ icon.icon(name) }}")
            ->render(['name' => $name]));
    }

    #[Test]
    #[TestDox('A known icon renders as a decorative inline SVG on the 24-grid with currentColor stroke.')]
    public function it_renders_a_known_icon_as_an_inline_svg(): void
    {
        $svg = $this->renderIcon("{{ icon.icon('leaf') }}");

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringEndsWith('</svg>', $svg);
        self::assertStringContainsString('viewBox="0 0 24 24"', $svg);
        self::assertStringContainsString('stroke="currentColor"', $svg);
        self::assertStringContainsString('fill="none"', $svg);
        self::assertStringContainsString('aria-hidden="true"', $svg);
        // The leaf path geometry is present.
        self::assertStringContainsString('<path d="M11 20A7 7', $svg);
    }

    #[Test]
    #[TestDox('Defaults are 16px / 1.6 stroke; size, stroke and class arguments override them.')]
    public function it_applies_size_stroke_and_class(): void
    {
        $default = $this->renderIcon("{{ icon.icon('bell') }}");
        self::assertStringContainsString('width="16"', $default);
        self::assertStringContainsString('height="16"', $default);
        self::assertStringContainsString('stroke-width="1.6"', $default);
        self::assertStringNotContainsString('class=', $default);

        $custom = $this->renderIcon("{{ icon.icon('bell', 24, 2, 'text-litrec-secondary rotate-90') }}");
        self::assertStringContainsString('width="24"', $custom);
        self::assertStringContainsString('height="24"', $custom);
        self::assertStringContainsString('stroke-width="2"', $custom);
        self::assertStringContainsString('class="text-litrec-secondary rotate-90"', $custom);
    }

    #[Test]
    #[TestDox('An unknown icon name renders an empty, invisible SVG instead of erroring.')]
    public function it_renders_nothing_for_an_unknown_name(): void
    {
        $svg = $this->renderNamed('definitely-not-an-icon');

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringEndsWith('</svg>', $svg);
        self::assertStringNotContainsString('<path', $svg);
        self::assertStringNotContainsString('<circle', $svg);
        self::assertStringNotContainsString('<rect', $svg);
    }

    #[Test]
    #[DataProvider('iconNames')]
    #[TestDox('Every documented icon name resolves to non-empty geometry.')]
    public function it_exposes_every_documented_icon_name(string $name): void
    {
        $svg = $this->renderNamed($name);

        self::assertTrue(
            str_contains($svg, '<path') || str_contains($svg, '<circle') || str_contains($svg, '<rect'),
            "Icon '{$name}' rendered no geometry.",
        );
    }

    /**
     * The full set of names the macro supports. Intentionally hard-coded and
     * kept in sync with components/_icon.html.twig by hand: this provider IS
     * the contract that pins the documented icon set, so adding/removing an
     * icon must be a deliberate two-file change.
     *
     * @return iterable<string, array{string}>
     */
    public static function iconNames(): iterable
    {
        $names = [
            'search', 'trash', 'plus', 'chevron', 'chevronUp', 'chevronR', 'user', 'users',
            'cart', 'info', 'bell', 'leaf', 'tree', 'calendar', 'heart', 'money', 'tag',
            'ticket', 'key', 'arrowUp', 'bolt', 'pin', 'check', 'grid', 'clock', 'print',
            'sun', 'moon',
        ];

        foreach ($names as $name) {
            yield $name => [$name];
        }
    }
}
