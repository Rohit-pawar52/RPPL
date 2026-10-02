<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an Excel .xlsx file into plain text rows, without
 * a spreadsheet package: an .xlsx is a zip of XML files, and an import only
 * needs the cell values. Shared and inline strings are resolved, and a
 * number in a date-formatted cell (how Excel stores a typed date) is turned
 * back into "Y-m-d H:i:s" text, so the rows look like what a CSV export of
 * the same sheet would give.
 */
final class XlsxReader
{
    /**
     * Largest XML part read, so a tiny zip that inflates to gigabytes
     * (a zip bomb) is refused rather than loaded.
     */
    private const MAX_PART_BYTES = 20 * 1024 * 1024;

    public static function looksLikeXlsx(string $content): bool
    {
        return str_starts_with($content, "PK\x03\x04");
    }

    /**
     * @return list<list<?string>> one list per spreadsheet row (empty rows
     *                             included, so row numbers line up), cells
     *                             indexed from column A
     */
    public static function records(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened.');
        }

        try {
            $strings = self::sharedStrings($zip);
            $dateStyles = self::dateStyles($zip);
            $sheet = self::xml($zip, self::firstSheetPath($zip));
        } finally {
            $zip->close();
        }

        $records = [];
        $next = 1;

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $number = (int) ($row['r'] ?? $next);

            while ($next < $number) {
                $records[] = [];
                $next++;
            }

            $cells = [];

            foreach ($row->c as $cell) {
                $column = self::columnIndex((string) $cell['r']) ?? count($cells);
                $cells[$column] = self::value($cell, $strings, $dateStyles);
            }

            $width = $cells === [] ? 0 : max(array_keys($cells)) + 1;
            $records[] = array_map(fn (int $i) => $cells[$i] ?? null, range(0, max(0, $width - 1)));
            $next = $number + 1;
        }

        return $records;
    }

    private static function value(SimpleXMLElement $cell, array $strings, array $dateStyles): ?string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text = (string) ($cell->is->t ?? '');

            foreach ($cell->is->r ?? [] as $run) {
                $text .= (string) $run->t;
            }

            return $text;
        }

        if (! isset($cell->v)) {
            return null;
        }

        $raw = (string) $cell->v;

        return match ($type) {
            's' => $strings[(int) $raw] ?? null,
            'str' => $raw,
            'b' => $raw === '1' ? '1' : '0',
            'e' => null,
            default => self::number($raw, isset($dateStyles[(int) $cell['s']])),
        };
    }

    private static function number(string $raw, bool $isDate): string
    {
        if (! is_numeric($raw)) {
            return $raw;
        }

        if ($isDate) {
            return gmdate('Y-m-d H:i:s', (int) round(((float) $raw - 25569) * 86400));
        }

        // 9.87654321E9 is how a long number can be stored; a phone number
        // must come back as its digits.
        if (stripos($raw, 'e') !== false) {
            return sprintf('%.0f', (float) $raw);
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];

        foreach (self::xml($zip, 'xl/sharedStrings.xml')->si ?? [] as $item) {
            $text = (string) ($item->t ?? '');

            foreach ($item->r ?? [] as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * Style indexes whose number format is a date/time one.
     *
     * @return array<int, true>
     */
    private static function dateStyles(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/styles.xml') === false) {
            return [];
        }

        $styles = self::xml($zip, 'xl/styles.xml');
        $custom = [];

        foreach ($styles->numFmts->numFmt ?? [] as $format) {
            $custom[(int) $format['numFmtId']] = (string) $format['formatCode'];
        }

        $dates = [];
        $index = 0;

        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            $builtIn = ($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58);
            $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id] ?? '');

            if ($builtIn || ($code !== '' && preg_match('/[dmyhs]/i', $code))) {
                $dates[$index] = true;
            }

            $index++;
        }

        return $dates;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';

        if ($zip->locateName('xl/workbook.xml') === false || $zip->locateName('xl/_rels/workbook.xml.rels') === false) {
            return $fallback;
        }

        $first = self::xml($zip, 'xl/workbook.xml')->sheets->sheet[0] ?? null;
        $id = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        foreach (self::xml($zip, 'xl/_rels/workbook.xml.rels')->Relationship ?? [] as $relationship) {
            if ($id !== '' && (string) $relationship['Id'] === $id) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return $fallback;
    }

    private static function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            throw new RuntimeException('The Excel file is missing its sheet.');
        }

        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException('The Excel file is too large to read.');
        }

        $xml = simplexml_load_string((string) $zip->getFromName($name), SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($xml === false) {
            throw new RuntimeException('The Excel file could not be read.');
        }

        return $xml;
    }

    private static function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)\d+$/', $reference, $parts)) {
            return null;
        }

        $index = 0;

        foreach (str_split($parts[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
