<?php

namespace App\View\Composers;

use App\Services\Settings\SettingsService;
use App\Support\Branding;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Shares one $branding value object with the specific layouts/partials/
 * standalone documents that actually render presentation settings
 * (Phase 3.44B3) — registered against an explicit list of view names in
 * AppServiceProvider::configureBranding(), never a '*' wildcard, so
 * SettingsService is only ever read once per request per view that
 * genuinely needs it, not on every view render.
 */
class BrandingComposer
{
    public function __construct(private readonly SettingsService $settings) {}

    public function compose(View $view): void
    {
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
        ));
    }

    private function resolveUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
