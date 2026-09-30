<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds tiny, deterministic, obviously-placeholder PNG images (no text,
 * non-photographic) WITHOUT the GD extension, by packing the PNG chunks
 * by hand. Used only so demo News/Photo rows have a real file behind them
 * on the public disk — never a broken image reference.
 */
class DemoImage
{
    private const NAVY = [0x0B, 0x2E, 0x3F];

    private const GREEN = [0x16, 0xA3, 0x4A];

    /**
     * Raw PNG bytes: a navy field with a horizontal green band whose
     * vertical position depends on $variant, plus a thin green stripe
     * along the bottom so each variant is visibly distinct.
     */
    public static function png(int $variant = 1, int $width = 640, int $height = 360): string
    {
        $bandTop = (int) ($height * (0.25 + 0.1 * (($variant - 1) % 4)));
        $bandBottom = $bandTop + (int) ($height * 0.18);

        $navy = pack('C*', ...self::NAVY);
        $green = pack('C*', ...self::GREEN);

        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $isGreen = ($y >= $bandTop && $y < $bandBottom) || $y >= $height - 8;
            $raw .= "\x00".str_repeat($isGreen ? $green : $navy, $width);
        }

        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', $header)
            .self::chunk('IDAT', gzcompress($raw, 9))
            .self::chunk('IEND', '');
    }

    /**
     * Stores the placeholder on the public disk only when no file exists
     * at $path yet, and returns the path.
     */
    public static function store(string $path, int $variant = 1): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $disk->put($path, self::png($variant));
        }

        return $path;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
