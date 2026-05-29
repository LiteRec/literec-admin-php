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
 * Pins the shared Eagleton badge: _badge.html.twig renders a single lr-badge
 * implementation with a sanitised variant, and _status_badge.html.twig maps a
 * TransactionStatus value onto that same partial — so there is exactly one
 * badge implementation, not two.
 */
#[Small]
final class BadgeComponentTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 3) . '/templates');
        $this->twig = new Environment($loader);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        return trim($this->twig->render($template, $context));
    }

    #[Test]
    #[TestDox('_badge renders the lr-badge pill with the requested variant and label.')]
    public function it_renders_a_badge_with_variant_and_label(): void
    {
        $html = $this->render('components/_badge.html.twig', ['label' => 'Excellent', 'variant' => 'success']);

        self::assertStringContainsString('class="lr-badge success"', $html);
        self::assertStringContainsString('Excellent', $html);
    }

    #[Test]
    #[TestDox('_badge falls back to the neutral variant for an unknown variant.')]
    public function it_falls_back_to_neutral_for_an_unknown_variant(): void
    {
        $html = $this->render('components/_badge.html.twig', ['label' => 'X', 'variant' => 'chartreuse']);

        self::assertStringContainsString('class="lr-badge neutral"', $html);
    }

    #[Test]
    #[TestDox('_badge appends an optional extra class.')]
    public function it_appends_an_optional_class(): void
    {
        $html = $this->render(
            'components/_badge.html.twig',
            ['label' => 'X', 'variant' => 'info', 'class' => 'uppercase'],
        );

        self::assertStringContainsString('class="lr-badge info uppercase"', $html);
    }

    #[Test]
    #[DataProvider('statusVariants')]
    #[TestDox('_status_badge maps each transaction status onto the matching lr-badge variant.')]
    public function it_maps_transaction_status_to_a_badge_variant(string $status, string $variant): void
    {
        $html = $this->render('components/_status_badge.html.twig', ['status' => $status]);

        self::assertStringContainsString('class="lr-badge ' . $variant . '"', $html);
        self::assertStringContainsString(ucfirst($status), $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function statusVariants(): iterable
    {
        yield 'succeeded -> success' => ['succeeded', 'success'];
        yield 'pending -> warning' => ['pending', 'warning'];
        yield 'failed -> danger' => ['failed', 'danger'];
        yield 'refunded -> info' => ['refunded', 'info'];
        yield 'unknown -> neutral' => ['voided', 'neutral'];
    }
}
