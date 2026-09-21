<?php

namespace App\Support;

/**
 * Picks a readable foreground (#ffffff or #000000) for a given
 * background color (Phase 3.44B4) — an admin can configure ANY valid
 * hex for button_color/primary_color, including very light or very
 * dark values, so the foreground can never be hardcoded to white.
 * Deterministic and centralized: every themed surface that needs a
 * readable foreground goes through this one place.
 *
 * Uses the WCAG relative-luminance formula, not a full color-science
 * library — this only ever has to choose between two fixed options.
 */
final class ForegroundContrast
{
    /**
     * @param  string  $backgroundHex  Must already be a validated
     *                                 #RRGGBB string (see HexColor::sanitize()) — this performs no
     *                                 validation of its own.
     */
    public static function for(string $backgroundHex): string
    {
        return self::relativeLuminance($backgroundHex) > 0.5 ? '#000000' : '#ffffff';
    }

    private static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        return 0.2126 * self::linearize($r) + 0.7152 * self::linearize($g) + 0.0722 * self::linearize($b);
    }

    /**
     * @return array{0: float, 1: float, 2: float} Each channel as 0..1.
     */
    private static function channels(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
