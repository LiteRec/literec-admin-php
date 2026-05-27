<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Barcode;

use App\Inventory\Domain\Barcode\BarcodeRenderer;
use Picqer\Barcode\Barcode;
use Picqer\Barcode\Renderers\HtmlRenderer;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Production CODE128 implementation of the {@see BarcodeRenderer}
 * port. Wraps picqer/php-barcode-generator and is the only place in
 * the codebase that imports its types — Deptrac forbids
 * `Picqer\*` from the Domain and Application layers.
 *
 * SVG (not PNG) is the wire format on purpose: picqer's PngRenderer
 * requires either the GD or the Imagick PHP extension and the
 * LiteRecAdmin FrankenPHP image ships neither. SVG renders crisply on
 * print media and embeds inline without any binary handling.
 */
final class Code128BarcodeRenderer implements BarcodeRenderer
{
    private const float DEFAULT_WIDTH = 300.0;

    private const float DEFAULT_HEIGHT = 80.0;

    public function renderHtml(string $payload): string
    {
        return (new HtmlRenderer())->render(
            $this->encode($payload),
            self::DEFAULT_WIDTH,
            self::DEFAULT_HEIGHT,
        );
    }

    public function renderSvgBase64(string $payload): string
    {
        $svg = (new SvgRenderer())->render(
            $this->encode($payload),
            self::DEFAULT_WIDTH,
            self::DEFAULT_HEIGHT,
        );

        return base64_encode($svg);
    }

    private function encode(string $payload): Barcode
    {
        return (new TypeCode128())->getBarcode($payload);
    }
}
