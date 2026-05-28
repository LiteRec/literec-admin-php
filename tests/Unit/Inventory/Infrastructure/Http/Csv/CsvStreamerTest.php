<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Http\Csv;

use App\Inventory\Infrastructure\Http\Csv\CsvStreamer;
use Generator;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unit coverage for the shared CSV streaming helper (LRA-91). Verifies
 * the header row, RFC 4180 escaping (doubled double-quotes), output
 * headers, and that the response uses StreamedResponse (deferred body).
 */
#[Small]
final class CsvStreamerTest extends TestCase
{
    private const string CONTENT_TYPE_HEADER = 'Content-Type';

    private const string CONTENT_DISPOSITION_HEADER = 'Content-Disposition';

    private const string ACCEL_BUFFERING_HEADER = 'X-Accel-Buffering';

    #[Test]
    #[TestDox('Streaming response sets text/csv content type, attachment disposition, and disabled accel buffering.')]
    public function streaming_response_sets_required_headers(): void
    {
        $streamer = new CsvStreamer();

        $response = $streamer->streamingResponse(
            rows: [],
            header: ['A', 'B'],
            filenameStem: 'my-report',
        );

        $contentType = (string) $response->headers->get(self::CONTENT_TYPE_HEADER);
        self::assertSame('text/csv; charset=utf-8', $contentType);

        $disposition = (string) $response->headers->get(self::CONTENT_DISPOSITION_HEADER);
        self::assertStringStartsWith('attachment; filename="my-report-', $disposition);
        self::assertStringEndsWith('.csv"', $disposition);

        self::assertSame('no', $response->headers->get(self::ACCEL_BUFFERING_HEADER));
    }

    #[Test]
    #[TestDox('Streaming response writes the header row first, then each yielded data row, RFC 4180 escaped.')]
    public function streaming_response_writes_header_and_rows(): void
    {
        $streamer = new CsvStreamer();

        $response = $streamer->streamingResponse(
            rows: self::sampleRows(),
            header: ['Col1', 'Col2'],
            filenameStem: 'sample',
        );

        $body = self::captureStreamedBody($response);

        self::assertStringContainsString("Col1,Col2\n", $body);
        self::assertStringContainsString('plain,1' . "\n", $body);
        // RFC 4180: embedded comma forces quoting; embedded quote
        // doubles. With escape='' fputcsv emits the doubled-quote
        // form ("a""b" instead of "a\"b").
        self::assertStringContainsString('"with, comma",2', $body);
        self::assertStringContainsString('"with ""quote""",3', $body);
    }

    #[Test]
    #[TestDox('A flushEvery of 0 is normalized up to 1 so the flush cadence never divides by zero.')]
    public function flush_every_zero_is_normalised(): void
    {
        $streamer = new CsvStreamer();

        $response = $streamer->streamingResponse(
            rows: [['only']],
            header: ['H'],
            filenameStem: 'stem',
            flushEvery: 0,
        );

        // Sending the body must not throw.
        $body = self::captureStreamedBody($response);
        self::assertStringContainsString("H\nonly\n", $body);
    }

    /**
     * @return Generator<int, list<string>, mixed, void>
     */
    private static function sampleRows(): Generator
    {
        yield ['plain', '1'];
        yield ['with, comma', '2'];
        yield ['with "quote"', '3'];
    }

    /**
     * Invokes the StreamedResponse callback under a buffered-output
     * sink that absorbs the helper's internal ob_flush() / flush()
     * calls. A naive ob_start + sendContent does not work here because
     * the helper's ob_flush() at end-of-stream pushes the captured
     * bytes to the parent buffer (or SAPI), so a subsequent
     * ob_get_clean() sees an empty string. Stacking a second buffer
     * means ob_flush() merely promotes data into the outer buffer,
     * which we then read back via ob_get_clean(). The production code
     * path is unaffected — operators receive bytes immediately as
     * intended.
     */
    private static function captureStreamedBody(StreamedResponse $response): string
    {
        ob_start();   // outer: collects the eventual data
        ob_start();   // inner: the helper's ob_flush() promotes into outer
        $response->sendContent();
        if (ob_get_level() > 1) {
            ob_end_clean();   // discard the inner buffer (already flushed)
        }
        return (string) ob_get_clean();
    }
}
