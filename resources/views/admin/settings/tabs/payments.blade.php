@php
    $upiQrPath = $settings->get('payment.upi_qr_path');
@endphp

{{-- UPI details for the public player registration form. A separate form and
     action from Razorpay, so saving one never touches the other. Side by side
     on wide screens, one under the other on a phone. --}}
<div class="grid gap-x-10 gap-y-6 lg:grid-cols-2 lg:items-start">
<form method="POST" action="{{ route('admin.settings.upi.update') }}" enctype="multipart/form-data" novalidate class="border-b border-neutral-200 pb-6 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-10">
    @csrf
    @method('PUT')

    <p class="text-[13px] font-semibold text-neutral-800">{{ __('UPI payment details') }}</p>
    <p class="mb-3.5 mt-0.5 text-[11px] text-neutral-400">
        {{ __('Shown on the public player registration form so players know where to pay the registration fee.') }}
        {{ __('Leave both empty to show only the general payment instructions.') }}
    </p>

    <x-form.input name="upi_id" label="UPI ID" :value="$settings->get('payment.upi_id')" maxlength="100" placeholder="name@bank" :help="__('Also used for the “Pay with a UPI app” button on phones.')" />

    <x-form.image-upload
        name="upi_qr"
        :label="__('UPI QR code')"
        :current="$upiQrPath"
        kind="image"
        box-class="h-28 w-28 rounded-xl"
        :empty-text="__('Click the picture to add the QR code')"
        remove-name="remove_upi_qr"
        :remove-label="__('Remove QR code')"
        :help="__('Shown on the public registration form.')"
    />

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        {{ __('Save UPI details') }}
    </button>
</form>

<form method="POST" action="{{ route('admin.settings.payments.update') }}" novalidate>
    @csrf
    @method('PUT')

    <p class="mb-3.5 text-[13px] font-semibold text-neutral-800">Razorpay</p>

    <div class="grid gap-x-4 sm:grid-cols-2">
        <x-form.select
            name="razorpay_enabled"
            label="Razorpay"
            :options="['0' => __('Disabled'), '1' => __('Enabled')]"
            :value="$settings->boolean('payment.razorpay_enabled') ? '1' : '0'"
        />

        <x-form.select
            name="razorpay_mode"
            :label="__('Mode')"
            :options="['test' => __('Test'), 'live' => __('Live')]"
            :value="$settings->get('payment.razorpay_mode')"
        />
    </div>

    <x-form.input name="razorpay_key_id" :label="__('Key ID')" :value="$settings->get('payment.razorpay_key_id')" maxlength="255" />

    {{-- Secrets are never decrypted here — x-form.input never writes a
         `value` attribute for type="password", so this input always
         renders blank regardless of what's stored. The status line
         below is driven only by SettingsService::hasEncryptedValue(),
         which reports presence without ever touching the plaintext. --}}
    <x-form.input name="razorpay_key_secret" :label="__('Key Secret')" type="password" maxlength="255" autocomplete="off" />
    <p class="-mt-2.5 mb-3.5 text-[11px] {{ $settings->hasEncryptedValue('payment.razorpay_key_secret') ? 'text-green-600' : 'text-neutral-400' }}">
        {{ $settings->hasEncryptedValue('payment.razorpay_key_secret') ? __('Configured — leave blank to keep the existing value.') : __('Not configured.') }}
    </p>

    <x-form.input name="razorpay_webhook_secret" :label="__('Webhook Secret')" type="password" maxlength="255" autocomplete="off" />
    <p class="-mt-2.5 mb-3.5 text-[11px] {{ $settings->hasEncryptedValue('payment.razorpay_webhook_secret') ? 'text-green-600' : 'text-neutral-400' }}">
        {{ $settings->hasEncryptedValue('payment.razorpay_webhook_secret') ? __('Configured — leave blank to keep the existing value.') : __('Not configured.') }}
    </p>

    <p class="mb-3.5 text-[11px] text-neutral-400">
        {{ __('These are stored preferences only — no Razorpay integration exists yet.') }}
    </p>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        {{ __('Save changes') }}
    </button>
</form>
</div>
