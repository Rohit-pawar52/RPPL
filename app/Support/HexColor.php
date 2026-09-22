<?php

namespace App\Support;

/**
 * The one place that decides whether a persisted color setting is a
 * genuine #RRGGBB value safe to output as CSS (Phase 3.44B4). Settings
 * are already validated as strict six-digit hex on write (see
 * UpdateGeneralSettingsRequest), but a value could still reach here
 * malformed — legacy data, manual DB edits — and a color is rendered
 * straight into a <style> block, so this is the last line of defense
 * before anything reaches CSS output, never trusting the stored value
 * on its own.
 */
final class HexColor
{
    private const PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * Returns $value unchanged if it's a valid #RRGGBB string, otherwise
     * $fallback (expected to already be a known-good registry default —
     * never re-validated itself, so callers must pass a trusted value).
     */
    public static function sanitize(?string $value, string $fallback): string
    {
        return self::isValid($value) ? $value : $fallback;
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && preg_match(self::PATTERN, $value) === 1;
    }
}
