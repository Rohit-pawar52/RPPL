<?php

namespace App\Services\Settings;

use App\Models\Setting;

/**
 * The ONE allow-list of every setting RPPL supports (Phase 3.44B1) — a
 * dotted "group.key" name mapped to its type and default. Nothing else
 * in the application may invent a setting key: SettingsService::set()
 * rejects anything not listed here, and every default a caller sees
 * when a setting has never been persisted comes from this file alone,
 * never duplicated in a controller/view/service.
 *
 * Static/stateless by design — this is pure lookup data, no I/O, no
 * container binding needed (matches this project's existing static
 * pure-helper convention, e.g. Player::normalizePhone()).
 *
 * This phase only establishes the registry/data layer — no admin UI
 * reads or writes these yet (Phase 3.44B2).
 */
final class SettingsRegistry
{
    /**
     * @var array<string, array{type: string, default: mixed}>
     */
    private const DEFINITIONS = [
        // General — sitewide branding. primary_color/button_color default
        // to Tailwind's blue-600 (#2563eb) and secondary_color to
        // neutral-500 (#737373), matching the colors already hardcoded
        // throughout the current Blade views — the same "sensible
        // current-brand-compatible fallback" the Phase 3.44A audit asked
        // for, not an arbitrary new palette.
        'general.application_name' => ['type' => Setting::TYPE_STRING, 'default' => 'RajaBhoj Pawar Premier League'],
        'general.short_name' => ['type' => Setting::TYPE_STRING, 'default' => 'RPPL'],
        'general.tagline' => ['type' => Setting::TYPE_STRING, 'default' => null],
        'general.logo_path' => ['type' => Setting::TYPE_IMAGE, 'default' => null],
        'general.favicon_path' => ['type' => Setting::TYPE_IMAGE, 'default' => null],
        'general.primary_color' => ['type' => Setting::TYPE_COLOR, 'default' => '#2563eb'],
        'general.secondary_color' => ['type' => Setting::TYPE_COLOR, 'default' => '#737373'],
        'general.button_color' => ['type' => Setting::TYPE_COLOR, 'default' => '#2563eb'],
        // Global appearance for the public announcement ticker (Phase
        // 3.45) — shared by every ticker message, never one color per
        // announcement. Defaults to the same brand blue on white text,
        // a legible high-visibility notice-bar look out of the box.
        'general.announcement_background_color' => ['type' => Setting::TYPE_COLOR, 'default' => '#2563eb'],
        'general.announcement_text_color' => ['type' => Setting::TYPE_COLOR, 'default' => '#ffffff'],

        // System — system.display_timezone is a DISPLAY preference only;
        // it must never be used to change config('app.timezone') or the
        // PHP/Laravel runtime timezone (see the Phase 3.44A audit).
        'system.maintenance_mode' => ['type' => Setting::TYPE_BOOLEAN, 'default' => false],
        'system.maintenance_message' => ['type' => Setting::TYPE_TEXT, 'default' => null],
        'system.currency' => ['type' => Setting::TYPE_STRING, 'default' => 'INR'],
        'system.currency_symbol' => ['type' => Setting::TYPE_STRING, 'default' => '₹'],
        'system.display_timezone' => ['type' => Setting::TYPE_STRING, 'default' => 'Asia/Kolkata'],

        // Contact — no current UI renders any of these; this phase only
        // makes them storable.
        'contact.email' => ['type' => Setting::TYPE_STRING, 'default' => null],
        'contact.phone' => ['type' => Setting::TYPE_STRING, 'default' => null],
        'contact.whatsapp' => ['type' => Setting::TYPE_STRING, 'default' => null],
        'contact.address' => ['type' => Setting::TYPE_TEXT, 'default' => null],

        // Finance — Phase 3.48. This is a per-edition TARGET total
        // expected from each committee member, reached across any number
        // of installment payments — never a minimum enforced on a single
        // payment. Deliberately a GLOBAL setting, not stored per Edition
        // (an explicit product decision that overrode the original
        // roadmap proposal): changing it changes the displayed dues
        // target for every edition's committee dues calculation the next
        // time it's viewed, including past editions — there is no
        // per-edition historical snapshot of this value. Contribution
        // AMOUNTS already recorded are never affected either way.
        'finance.committee_minimum_contribution' => ['type' => Setting::TYPE_DECIMAL, 'default' => 1000.00],

        // Payment — inert configuration only. No Razorpay package is
        // installed and nothing reads these yet.
        'payment.razorpay_enabled' => ['type' => Setting::TYPE_BOOLEAN, 'default' => false],
        'payment.razorpay_mode' => ['type' => Setting::TYPE_STRING, 'default' => 'test'],
        'payment.razorpay_key_id' => ['type' => Setting::TYPE_STRING, 'default' => null],
        'payment.razorpay_key_secret' => ['type' => Setting::TYPE_ENCRYPTED, 'default' => null],
        'payment.razorpay_webhook_secret' => ['type' => Setting::TYPE_ENCRYPTED, 'default' => null],

        // Public
        'public.footer_text' => ['type' => Setting::TYPE_TEXT, 'default' => null],
    ];

    public static function has(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    public static function type(string $key): ?string
    {
        return self::DEFINITIONS[$key]['type'] ?? null;
    }

    public static function default(string $key): mixed
    {
        return self::DEFINITIONS[$key]['default'] ?? null;
    }
}
