<form method="POST" action="{{ route('admin.settings.payments.update') }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.select
        name="razorpay_enabled"
        label="Razorpay"
        :options="['0' => 'Disabled', '1' => 'Enabled']"
        :value="$settings->boolean('payment.razorpay_enabled') ? '1' : '0'"
    />

    <x-form.select
        name="razorpay_mode"
        label="Mode"
        :options="['test' => 'Test', 'live' => 'Live']"
        :value="$settings->get('payment.razorpay_mode')"
    />

    <x-form.input name="razorpay_key_id" label="Key ID" :value="$settings->get('payment.razorpay_key_id')" maxlength="255" />

    {{-- Secrets are never decrypted here — x-form.input never writes a
         `value` attribute for type="password", so this input always
         renders blank regardless of what's stored. The status line
         below is driven only by SettingsService::hasEncryptedValue(),
         which reports presence without ever touching the plaintext. --}}
    <x-form.input name="razorpay_key_secret" label="Key Secret" type="password" maxlength="255" autocomplete="off" />
    <p class="-mt-2.5 mb-3.5 text-[11px] {{ $settings->hasEncryptedValue('payment.razorpay_key_secret') ? 'text-green-600' : 'text-neutral-400' }}">
        {{ $settings->hasEncryptedValue('payment.razorpay_key_secret') ? 'Configured — leave blank to keep the existing value.' : 'Not configured.' }}
    </p>

    <x-form.input name="razorpay_webhook_secret" label="Webhook Secret" type="password" maxlength="255" autocomplete="off" />
    <p class="-mt-2.5 mb-3.5 text-[11px] {{ $settings->hasEncryptedValue('payment.razorpay_webhook_secret') ? 'text-green-600' : 'text-neutral-400' }}">
        {{ $settings->hasEncryptedValue('payment.razorpay_webhook_secret') ? 'Configured — leave blank to keep the existing value.' : 'Not configured.' }}
    </p>

    <p class="mb-3.5 text-[11px] text-neutral-400">
        These are stored preferences only — no Razorpay integration exists yet.
    </p>

    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-[13px] font-medium text-white hover:bg-blue-500">
        Save changes
    </button>
</form>
