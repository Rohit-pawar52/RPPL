<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateContactSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdatePaymentSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdatePublicWebsiteSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateSystemSettingsRequest;
use App\Services\Settings\BrandingUploadService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin management of the Settings module's persisted values (Phase
 * 3.44B2). Gated behind the existing "manage-tournament" Gate, the same
 * broad admin-only check ReportsController/DataCleanupController use for
 * a non-Policy, non-resource admin page.
 *
 * Deliberately narrow: this controller only reads/writes settings and
 * branding files through SettingsService/BrandingUploadService. It never
 * touches maintenance-mode enforcement, dynamic branding output, or
 * Razorpay/Firebase integration — those remain out of scope until a
 * later phase actually consumes these values.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BrandingUploadService $branding,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-tournament');

        $tab = $request->query('tab', 'general');

        if (! in_array($tab, ['general', 'contact', 'system', 'payments', 'public-website'], true)) {
            $tab = 'general';
        }

        return view('admin.settings.index', [
            'activeTab' => $tab,
            'settings' => $this->settings,
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        // Removal takes priority over a same-request replacement upload
        // being present at all — a request that somehow sends both is
        // ambiguous, and "remove" is the more explicit, less surprising
        // instruction to honor.
        if ($request->boolean('remove_logo')) {
            $this->branding->removeLogo();
        } elseif ($request->hasFile('logo')) {
            $this->branding->replaceLogo($request->file('logo'));
        }

        if ($request->boolean('remove_favicon')) {
            $this->branding->removeFavicon();
        } elseif ($request->hasFile('favicon')) {
            $this->branding->replaceFavicon($request->file('favicon'));
        }

        $this->settings->setMany([
            'general.application_name' => $data['application_name'],
            'general.short_name' => $data['short_name'],
            'general.tagline' => $data['tagline'] ?? null,
            'general.primary_color' => $data['primary_color'],
            'general.secondary_color' => $data['secondary_color'],
            'general.button_color' => $data['button_color'],
            'general.announcement_background_color' => $data['announcement_background_color'],
            'general.announcement_text_color' => $data['announcement_text_color'],
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'general'])
            ->with('success', 'General settings updated.');
    }

    public function updateContact(UpdateContactSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        $this->settings->setMany([
            'contact.email' => $data['email'] ?? null,
            'contact.phone' => $data['phone'] ?? null,
            'contact.whatsapp' => $data['whatsapp'] ?? null,
            'contact.address' => $data['address'] ?? null,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'contact'])
            ->with('success', 'Contact settings updated.');
    }

    public function updateSystem(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        $this->settings->setMany([
            'system.maintenance_mode' => $request->boolean('maintenance_mode'),
            'system.maintenance_message' => $data['maintenance_message'] ?? null,
            'system.currency' => $data['currency'],
            'system.currency_symbol' => $data['currency_symbol'],
            'system.display_timezone' => $data['display_timezone'],
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'system'])
            ->with('success', 'System settings updated.');
    }

    public function updatePayments(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        $values = [
            'payment.razorpay_enabled' => $request->boolean('razorpay_enabled'),
            'payment.razorpay_mode' => $data['razorpay_mode'],
            'payment.razorpay_key_id' => $data['razorpay_key_id'] ?? null,
        ];

        // Blank means "keep the existing secret" — only ever included in
        // the write when the admin actually typed a new, non-blank
        // value, so an accidental blank submit can never wipe a secret
        // that's already configured.
        if (filled($data['razorpay_key_secret'] ?? null)) {
            $values['payment.razorpay_key_secret'] = $data['razorpay_key_secret'];
        }

        if (filled($data['razorpay_webhook_secret'] ?? null)) {
            $values['payment.razorpay_webhook_secret'] = $data['razorpay_webhook_secret'];
        }

        $this->settings->setMany($values);

        return redirect()->route('admin.settings.index', ['tab' => 'payments'])
            ->with('success', 'Payment settings updated.');
    }

    public function updatePublicWebsite(UpdatePublicWebsiteSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        $this->settings->setMany([
            'public.footer_text' => $data['footer_text'] ?? null,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'public-website'])
            ->with('success', 'Public website settings updated.');
    }
}
