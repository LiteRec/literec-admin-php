<?php

declare(strict_types=1);

namespace App\Tests\Support\Inventory;

use App\Inventory\Domain\Barcode\BarcodeRenderer;

/**
 * Test double for {@see BarcodeRenderer}. Returns canned strings that
 * embed the payload verbatim so a functional/unit test can assert the
 * code under test invoked the port with the expected value without
 * dragging in picqer/php-barcode-generator at test time.
 */
final class InMemoryBarcodeRenderer implements BarcodeRenderer
{
    public function renderHtml(string $payload): string
    {
        return sprintf(
            '<svg data-test-barcode="%s">%s</svg>',
            htmlspecialchars($payload, ENT_QUOTES | ENT_HTML5),
            htmlspecialchars($payload, ENT_QUOTES | ENT_HTML5),
        );
    }

    public function renderSvgBase64(string $payload): string
    {
        return base64_encode(sprintf(
            '<svg data-test-barcode="%s"/>',
            htmlspecialchars($payload, ENT_QUOTES | ENT_HTML5),
        ));
    }
}
