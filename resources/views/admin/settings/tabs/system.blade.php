<form method="POST" action="{{ route('admin.settings.system.update') }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.select
        name="maintenance_mode"
        label="Maintenance Mode"
        :options="['0' => 'Disabled', '1' => 'Enabled']"
        :value="$settings->boolean('system.maintenance_mode') ? '1' : '0'"
    />

    <div class="mb-3.5">
        <label for="maintenance_message" class="mb-1 block text-xs font-medium text-neutral-700">Maintenance Message</label>
        <textarea
            id="maintenance_message"
            name="maintenance_message"
            rows="3"
            maxlength="1000"
            class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('maintenance_message') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
        >{{ old('maintenance_message', $settings->get('system.maintenance_message')) }}</textarea>
        @error('maintenance_message')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <x-form.input name="currency" label="Currency Code" :value="$settings->get('system.currency')" required maxlength="10" />
    <x-form.input name="currency_symbol" label="Currency Symbol" :value="$settings->get('system.currency_symbol')" required maxlength="10" />
    <x-form.input name="display_timezone" label="Display Timezone" :value="$settings->get('system.display_timezone')" required placeholder="Asia/Kolkata" />

    <p class="mb-3.5 text-[11px] text-neutral-400">
        Maintenance mode and display timezone are live — enabling maintenance mode immediately blocks the public website, and the display timezone controls how dates/times are shown across the site. Currency/currency symbol are currently stored preferences only; nothing on the site reads them yet.
    </p>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
