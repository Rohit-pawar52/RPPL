<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateContactSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdatePaymentSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdatePublicWebsiteSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateSystemSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateUpiSettingsRequest;
use App\Models\User;
use App\Services\Settings\BrandingUploadService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin management of the Settings module's persisted values (Phase
 * 3.44B2). Every action, including just opening the page, needs the
 * settings.manage permission (there is no separate view permission for
 * settings). Checked inline, like ReportsController/DataCleanupController
 * do for a non-Policy, non-resource admin page.
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
        $this->authorizeSettings();

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
        $this->authorizeSettings();

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
            'general.button_hover_color' => $data['button_hover_color'] ?? null,
            'general.button_text_color' => $data['button_text_color'] ?? null,
            'general.link_hover_color' => $data['link_hover_color'] ?? null,
            'general.hover_color' => $data['hover_color'] ?? null,
            ...(isset($data['header_color']) ? ['general.header_color' => $data['header_color']] : []),
            ...(isset($data['button_shape']) ? ['general.button_shape' => $data['button_shape']] : []),
            'general.announcement_background_color' => $data['announcement_background_color'],
            'general.announcement_text_color' => $data['announcement_text_color'],
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'general'])
            ->with('success', __('General settings updated.'));
    }

    public function updateContact(UpdateContactSettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettings();

        $data = $request->validated();

        $this->settings->setMany([
            'contact.email' => $data['email'] ?? null,
            'contact.phone' => $data['phone'] ?? null,
            'contact.whatsapp' => $data['whatsapp'] ?? null,
            'contact.address' => $data['address'] ?? null,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'contact'])
            ->with('success', __('Contact settings updated.'));
    }

    public function updateSystem(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettings();

        $data = $request->validated();

        $values = [
            'system.maintenance_mode' => $request->boolean('maintenance_mode'),
            'system.maintenance_message' => $data['maintenance_message'] ?? null,
            'system.currency' => $data['currency'],
            'system.currency_symbol' => $data['currency_symbol'],
            'system.display_timezone' => $data['display_timezone'],
            'finance.committee_minimum_contribution' => $data['committee_minimum_contribution'],
        ];

        // Tournament-Day Morning Reminder fields are only written when
        // actually submitted — the System tab form always posts both (the
        // checkbox via a hidden "0" fallback), but any other caller that
        // omits them keeps the currently stored values untouched.
        if ($request->filled('tournament_day_reminder_enabled')) {
            $values['notifications.tournament_day_reminder_enabled'] = $request->boolean('tournament_day_reminder_enabled');
        }

        if (! empty($data['tournament_day_reminder_time'])) {
            $values['notifications.tournament_day_reminder_time'] = $data['tournament_day_reminder_time'];
        }

        if ($request->filled('failed_jobs_auto_cleanup_enabled')) {
            $values['system.failed_jobs_auto_cleanup_enabled'] = $request->boolean('failed_jobs_auto_cleanup_enabled');
        }

        if (! empty($data['failed_jobs_retention_days'])) {
            $values['system.failed_jobs_retention_days'] = (int) $data['failed_jobs_retention_days'];
        }

        $this->settings->setMany($values);

        return redirect()->route('admin.settings.index', ['tab' => 'system'])
            ->with('success', __('System settings updated.'));
    }

    public function updatePayments(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettings();

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
            ->with('success', __('Payment settings updated.'));
    }

    /**
     * The UPI ID and QR image the public registration form shows. Its own
     * form and action (not part of updatePayments) so saving it can never
     * touch the Razorpay values, and the reverse.
     */
    public function updateUpi(UpdateUpiSettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettings();

        $data = $request->validated();

        // Removal takes priority over a same-request replacement, exactly
        // like the logo/favicon in updateGeneral().
        if ($request->boolean('remove_upi_qr')) {
            $this->branding->removeUpiQr();
        } elseif ($request->hasFile('upi_qr')) {
            $this->branding->replaceUpiQr($request->file('upi_qr'));
        }

        $this->settings->setMany([
            'payment.upi_id' => filled($data['upi_id'] ?? null) ? $data['upi_id'] : null,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'payments'])
            ->with('success', __('UPI payment details updated.'));
    }

    public function updatePublicWebsite(UpdatePublicWebsiteSettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettings();

        $data = $request->validated();

        $this->settings->setMany([
            'public.footer_text' => $data['footer_text'] ?? null,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'public-website'])
            ->with('success', __('Public website settings updated.'));
    }

    private function authorizeSettings(): void
    {
        Gate::allowIf(fn (User $user) => $user->hasPermission('settings.manage'));
    }
}
