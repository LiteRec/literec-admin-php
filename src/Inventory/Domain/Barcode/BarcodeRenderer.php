<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Barcode;

/**
 * Domain port for rendering an InventoryItem's listing code as a
 * CODE128 barcode for the LRA-86 print page.
 *
 * Pure domain interface — no Picqer (or any other infrastructure
 * library) imports. The production adapter
 * {@see \App\Inventory\Infrastructure\Barcode\Code128BarcodeRenderer}
 * binds to picqer/php-barcode-generator and stays in the Infrastructure
 * layer; tests inject an in-memory fake that returns canned strings.
 *
 * Two flavors are exposed because the LRA-86 print template needs both
 * an inline HTML rendering (for high-fidelity browser-driven printing)
 * and a base64-encoded SVG (for embedding in templates that prefer a
 * data URL — e.g. an `<img src="data:image/svg+xml;base64,…">`).
 *
 * The original ticket called the second method `renderPngBase64`, but
 * PNG rendering via Picqer requires either the GD or the Imagick PHP
 * extension and neither is part of the LiteRecAdmin runtime image.
 * SVG renders without any extra extension, scales perfectly on print
 * media, and stays trivially testable with a string-match assertion.
 * The port spelling reflects the actual contract.
 */
interface BarcodeRenderer
{
    /**
     * Renders the payload as a self-contained block of HTML/SVG that
     * can be embedded directly in a Twig template via the `|raw`
     * filter. The exact markup is renderer-defined; callers should
     * treat the return value as opaque print-ready output.
     */
    public function renderHtml(string $payload): string;

    /**
     * Returns a base64-encoded SVG representation suitable for use
     * as the `src` of an `<img>` tag via a `data:image/svg+xml;base64,`
     * URL. Useful when the surrounding template wants a single
     * easily-cacheable image element instead of an inline DOM block.
     */
    public function renderSvgBase64(string $payload): string;
}
