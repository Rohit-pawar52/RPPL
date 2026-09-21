<?php

namespace App\View\Composers;

use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use App\Support\Branding;
use App\Support\ForegroundContrast;
use App\Support\HexColor;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Shares one $branding value object with the specific layouts/partials/
 * standalone documents that actually render presentation settings
 * (Phase 3.44B3, extended in 3.44B4 with theme colors) — registered
 * against an explicit list of view names in
 * AppServiceProvider::configureBranding(), never a '*' wildcard, so
 * SettingsService is only ever read once per request per view that
 * genuinely needs it, not on every view render.
 */
class BrandingComposer
{
    public function __construct(private readonly SettingsService $settings) {}

    public function compose(View $view): void
    {
        $primaryColor = $this->sanitizedColor('general.primary_color');
        $buttonColor = $this->sanitizedColor('general.button_color');

        $view->with('branding', new Branding(
            applicationName: $this->settings->get('general.application_name'),
            shortName: $this->settings->get('general.short_name'),
            tagline: $this->settings->get('general.tagline'),
            logoUrl: $this->resolveUrl($this->settings->get('general.logo_path')),
            faviconUrl: $this->resolveUrl($this->settings->get('general.favicon_path')),
            footerText: $this->settings->get('public.footer_text'),
            contactEmail: $this->settings->get('contact.email'),
            contactPhone: $this->settings->get('contact.phone'),
            contactWhatsapp: $this->settings->get('contact.whatsapp'),
            contactAddress: $this->settings->get('contact.address'),
            displayTimezone: $this->settings->get('system.display_timezone'),
            primaryColor: $primaryColor,
            primaryForegroundColor: ForegroundContrast::for($primaryColor),
            secondaryColor: $this->sanitizedColor('general.secondary_color'),
            buttonColor: $buttonColor,
            buttonForegroundColor: ForegroundContrast::for($buttonColor),
        ));
    }

    private function resolveUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * A registered color setting's persisted value, defensively
     * sanitized to a genuine #RRGGBB string — a malformed persisted
     * value (legacy/manual DB corruption, never possible through the
     * validated admin form) falls back to SettingsRegistry's own
     * default rather than ever reaching a <style> block unchecked.
     */
    private function sanitizedColor(string $key): string
    {
        return HexColor::sanitize($this->settings->get($key), SettingsRegistry::default($key));
    }
}
