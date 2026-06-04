import type { Download } from '@playwright/test';

export interface ParsedCsv {
  header: string[];
  rows: string[][];
  raw: string;
}

/**
 * Reads a triggered download and parses it as CSV. Used by the inventory
 * item-history (S6) and reports (S10) suites to assert export shape.
 *
 * The parser is intentionally simple (splits on commas, strips surrounding
 * quotes) and does not handle commas embedded inside quoted fields; the
 * consuming suites assert on header names and row counts, not on field values
 * that could contain delimiters.
 */
export async function readCsvDownload(download: Download): Promise<ParsedCsv> {
  const stream = await download.createReadStream();
  const chunks: Buffer[] = [];
  for await (const chunk of stream) {
    chunks.push(Buffer.from(chunk));
  }
  const raw = Buffer.concat(chunks).toString('utf8');
  const lines = raw.split(/\r?\n/).filter((line) => line.length > 0);
  const parseLine = (line: string): string[] =>
    line.split(',').map((cell) => cell.replace(/^"(.*)"$/, '$1'));
  const parsed = lines.map(parseLine);
  return { header: parsed[0] ?? [], rows: parsed.slice(1), raw };
}
