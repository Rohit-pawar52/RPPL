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

    <p class="text-[13px] font-semibold text-neutral-800">UPI payment details</p>
    <p class="mb-3.5 mt-0.5 text-[11px] text-neutral-400">
        Shown on the public player registration form so players know where to pay the registration fee.
        Leave both empty to show only the general payment instructions.
    </p>

    <x-form.input name="upi_id" label="UPI ID" :value="$settings->get('payment.upi_id')" maxlength="100" placeholder="name@bank" help="Also used for the “Pay with a UPI app” button on phones." />

    <div class="mb-3.5">
        <p class="mb-1 text-xs font-medium text-neutral-700">UPI QR code</p>
        <div class="flex items-center gap-3">
            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($upiQrPath)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($upiQrPath) }}" alt="UPI QR code" class="h-full w-full object-contain" />
                @else
                    <x-icon name="camera" class="h-6 w-6" />
                @endif
            </div>
            <div>
                <label class="cursor-pointer text-[11px] font-medium theme-link">
                    {{ $upiQrPath ? 'Replace QR code' : 'Upload QR code' }}
                    <input
                        type="file"
                        name="upi_qr"
                        accept="image/png,image/jpeg,image/webp"
                        class="hidden"
                        onchange="document.getElementById('upi-qr-filename').textContent = this.files[0]?.name ?? ''"
                    />
                </label>
                <p id="upi-qr-filename" class="text-[11px] text-neutral-400"></p>
                @if($upiQrPath)
                    <label class="mt-1 flex items-center gap-1 text-[11px] text-red-600">
                        <input type="checkbox" name="remove_upi_qr" value="1" class="rounded border-neutral-300" />
                        Remove QR code
                    </label>
                @endif
            </div>
        </div>
        @error('upi_qr')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save UPI details
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
            :options="['0' => 'Disabled', '1' => 'Enabled']"
            :value="$settings->boolean('payment.razorpay_enabled') ? '1' : '0'"
        />

        <x-form.select
            name="razorpay_mode"
            label="Mode"
            :options="['test' => 'Test', 'live' => 'Live']"
            :value="$settings->get('payment.razorpay_mode')"
        />
    </div>

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

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
</div>
