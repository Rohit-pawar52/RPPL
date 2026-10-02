<?php

namespace App\Support;

/**
 * How large a file a form can really accept on THIS server. A form that
 * promises "up to 10 MB" while PHP is set to upload_max_filesize=2M or
 * post_max_size=8M doesn't fail politely: PHP drops the file ("failed to
 * upload") or Laravel answers a bare 413 page. So the public registration
 * form asks for what it wants and is told what the server allows — the
 * hint, the browser-side check and the validation rule all use the one
 * resulting number.
 */
final class UploadLimits
{
    /**
     * Room kept inside post_max_size for the form's text fields and the
     * multipart boundaries around each file.
     */
    private const FORM_OVERHEAD_KB = 1024;

    /**
     * The largest size (in kilobytes, the unit of Laravel's `max` file
     * rule) each of $files files may have so that a form carrying that
     * many files of that size still fits: never above what was asked for,
     * never above upload_max_filesize, and all the files together (plus
     * the form data) never above post_max_size.
     *
     * $uploadMax / $postMax are php.ini notations ("40M"); null reads the
     * running server's own settings.
     */
    public static function perFileKilobytes(int $wantedKb, int $files, ?string $uploadMax = null, ?string $postMax = null): int
    {
        $limit = $wantedKb;

        $upload = self::bytes($uploadMax ?? (string) ini_get('upload_max_filesize'));

        if ($upload > 0) {
            $limit = min($limit, intdiv($upload, 1024));
        }

        $post = self::bytes($postMax ?? (string) ini_get('post_max_size'));

        if ($post > 0) {
            $limit = min($limit, intdiv(max(0, intdiv($post, 1024) - self::FORM_OVERHEAD_KB), max(1, $files)));
        }

        return max(1, $limit);
    }

    /**
     * A php.ini size ("512K", "40M", "1G", plain bytes) in bytes; 0 for
     * "no limit" (post_max_size=0) or a value that isn't a size.
     */
    public static function bytes(string $notation): int
    {
        $notation = trim($notation);

        if ($notation === '' || ! preg_match('/^(\d+(?:\.\d+)?)\s*([kmg]?)b?$/i', $notation, $parts)) {
            return 0;
        }

        $multiplier = match (strtolower($parts[2])) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            default => 1,
        };

        return (int) ((float) $parts[1] * $multiplier);
    }

    /**
     * Kilobytes as megabytes for a hint ("10", "4.5", "0.5").
     */
    public static function megabytes(int $kilobytes): string
    {
        $megabytes = number_format($kilobytes / 1024, $kilobytes >= 10240 ? 0 : 1, '.', '');

        return str_contains($megabytes, '.') ? rtrim(rtrim($megabytes, '0'), '.') : $megabytes;
    }
}
