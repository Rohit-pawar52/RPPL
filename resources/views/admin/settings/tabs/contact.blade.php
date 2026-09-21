<form method="POST" action="{{ route('admin.settings.contact.update') }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.input name="email" label="Email" type="email" :value="$settings->get('contact.email')" maxlength="255" />
    <x-form.input name="phone" label="Phone" :value="$settings->get('contact.phone')" maxlength="30" />
    <x-form.input name="whatsapp" label="WhatsApp" :value="$settings->get('contact.whatsapp')" maxlength="30" />

    <div class="mb-3.5">
        <label for="address" class="mb-1 block text-xs font-medium text-neutral-700">Address</label>
        <textarea
            id="address"
            name="address"
            rows="3"
            maxlength="500"
            class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('address') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 focus:ring-blue-100' }}"
        >{{ old('address', $settings->get('contact.address')) }}</textarea>
        @error('address')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-[13px] font-medium text-white hover:bg-blue-500">
        Save changes
    </button>
</form>
