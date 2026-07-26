<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSV export.
 *
 * The interesting part is not the commas — it is that a spreadsheet treats a
 * cell beginning with = + - @ (or tab/CR) as a FORMULA. An order placed under
 * the name `=cmd|'/c calc'!A1` becomes executable the moment someone opens the
 * export in Excel. That is CSV injection, and it is the reason every value here
 * passes through neutralise() on the way out.
 *
 * The data is our customers' own text, so it cannot simply be rejected at the
 * source — it has to be made inert at the boundary.
 */
class CsvExporter
{
    /** Characters that make a spreadsheet treat a cell as a formula. */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Stream rows as a CSV download.
     *
     * @param list<string>                 $headers
     * @param iterable<array<string|int, mixed>> $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) ?: 'export';

        // No output buffering games: send headers, then write straight out, so a
        // large export does not have to fit in memory.
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');

        if ($out === false) {
            return;
        }

        // BOM, so Excel opens UTF-8 correctly — ₹ and Devanagari survive.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array_map([$this, 'neutralise'], $headers));

        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'neutralise'], array_values($row)));
        }

        fclose($out);
    }

    /**
     * Make one cell inert.
     *
     * A leading apostrophe is the conventional fix, and it is what spreadsheet
     * software itself writes when forced to store a literal. The value is still
     * readable; it just is not executed.
     */
    public function neutralise(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        $text = (string) $value;

        if ($text === '') {
            return '';
        }

        // Strip control characters that could break the row apart.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        $first = mb_substr(ltrim($text), 0, 1);

        if (in_array($first, self::FORMULA_TRIGGERS, true)) {
            return "'" . $text;
        }

        return $text;
    }
}
