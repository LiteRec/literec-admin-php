<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Csv;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared CSV streaming helper for inventory exports (LRA-88 movement
 * history + LRA-91 reports). Owns the framework boundary: writes RFC
 * 4180-strict CSV to php://output, flushes on a configurable cadence,
 * and ships the right headers for browser download + Nginx pass-through.
 *
 * The helper itself is stateless — one instance is registered as a
 * service and shared by every CSV-emitting controller, so changes to
 * cadence, escaping, or header names happen in exactly one place.
 */
final class CsvStreamer
{
    /**
     * fputcsv() received an `escape` parameter in PHP 7.4 that defaulted
     * to backslash; PHP 8.4 deprecates the implicit default. Passing an
     * empty string opts into strict RFC 4180 escaping (doubled
     * double-quotes only) and silences the deprecation forever.
     */
    private const string ESCAPE = '';

    private const string DELIMITER = ',';

    private const string ENCLOSURE = '"';

    private const string CONTENT_TYPE = 'text/csv; charset=utf-8';

    /** Disables Nginx's response buffering so the client sees bytes as soon as we flush. */
    private const string ACCEL_BUFFERING_HEADER = 'X-Accel-Buffering';

    private const string ACCEL_BUFFERING_OFF = 'no';

    private const string FILENAME_TIMESTAMP_FORMAT = 'Ymd-His';

    /**
     * Backslash-escapes the two characters RFC 6266 calls out as
     * dangerous inside a quoted filename: the double-quote that ends
     * the quoted-string and the backslash that begins an escape.
     * Filenames in this codebase are static stems plus a UTC
     * timestamp, but the defensive escape keeps the helper safe if a
     * future caller passes operator-supplied content.
     */
    private const string FILENAME_ESCAPE_PATTERN = '/([\\\\"])/';

    private const string FILENAME_ESCAPE_REPLACEMENT = '\\\\$1';

    private const string OUTPUT_STREAM = 'php://output';

    private const string OUTPUT_OPEN_MODE = 'wb';

    private const string FAILED_TO_OPEN_OUTPUT = 'Unable to open php://output for CSV streaming.';

    /**
     * Default flush cadence (rows). Matches the read-model's per-chunk
     * SQL size so each storage round-trip produces one network flush.
     */
    public const int DEFAULT_FLUSH_EVERY = 500;

    /**
     * Builds a streaming CSV response.
     *
     * @param iterable<int, list<string>> $rows pre-formatted scalar rows; the helper does not coerce types
     * @param list<string> $header column headings emitted as the first line
     * @param string $filenameStem base filename without extension; a UTC `Ymd-His` stamp is appended
     * @param int $flushEvery output-buffer flush cadence (defaults to {@see self::DEFAULT_FLUSH_EVERY})
     */
    public function streamingResponse(
        iterable $rows,
        array $header,
        string $filenameStem,
        int $flushEvery = self::DEFAULT_FLUSH_EVERY,
    ): StreamedResponse {
        $response = new StreamedResponse();

        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::FILENAME_TIMESTAMP_FORMAT);
        $filename = sprintf('%s-%s.csv', $filenameStem, $timestamp);
        $escapedFilename = preg_replace(
            self::FILENAME_ESCAPE_PATTERN,
            self::FILENAME_ESCAPE_REPLACEMENT,
            $filename,
        );
        if ($escapedFilename === null) {
            // preg_replace returns null on PCRE engine failure; fall
            // back to a safe filename so the Content-Disposition header
            // is never malformed.
            $escapedFilename = sprintf('export-%s.csv', $timestamp);
        }

        $response->headers->set('Content-Type', self::CONTENT_TYPE);
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', $escapedFilename),
        );
        $response->headers->set(self::ACCEL_BUFFERING_HEADER, self::ACCEL_BUFFERING_OFF);

        $cadence = $flushEvery < 1 ? 1 : $flushEvery;

        $response->setCallback(function () use ($rows, $header, $cadence): void {
            $handle = fopen(self::OUTPUT_STREAM, self::OUTPUT_OPEN_MODE);
            if ($handle === false) {
                throw new LogicException(self::FAILED_TO_OPEN_OUTPUT);
            }

            try {
                fputcsv($handle, $header, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

                $written = 0;
                foreach ($rows as $row) {
                    fputcsv($handle, $row, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
                    $written++;
                    if ($written % $cadence === 0) {
                        self::flushOutput();
                    }
                }

                self::flushOutput();
            } finally {
                fclose($handle);
            }
        });

        return $response;
    }

    private static function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
