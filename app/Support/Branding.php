<?php

namespace App\Support;

/**
 * The small, presentation-safe slice of Settings that layouts/partials
 * actually need (Phase 3.44B3) — a plain immutable value object, never
 * the full SettingsRegistry/SettingsService surface. Shared into views
 * by BrandingComposer so a template never has to call
 * app(SettingsService::class)->get(...) directly.
 *
 * Deliberately excludes every payment/Razorpay key: nothing that reaches
 * a view through this object can ever be a secret, even by accident.
 */
final class Branding
{
    public function __construct(
        public readonly string $applicationName,
        public readonly string $shortName,
        public readonly ?string $tagline,
        public readonly ?string $logoUrl,
        public readonly ?string $faviconUrl,
        public readonly ?string $footerText,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $contactWhatsapp,
        public readonly ?string $contactAddress,
        public readonly string $displayTimezone,
    ) {}

    public function hasContactDetails(): bool
    {
        return filled($this->contactEmail)
            || filled($this->contactPhone)
            || filled($this->contactWhatsapp)
            || filled($this->contactAddress);
    }

    /**
     * A wa.me link built from the configured WhatsApp value, or null if
     * the value doesn't safely reduce to a plausible phone number (e.g.
     * an admin who typed a name/note instead of a number). Whenever
     * this is null, the caller renders the configured value as plain
     * text instead of a link — never guesses or invents a number.
     */
    public function whatsappLink(): ?string
    {
        if (blank($this->contactWhatsapp)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->contactWhatsapp);

        if ($digits === null || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return "https://wa.me/{$digits}";
    }
}
