<form method="POST" action="{{ route('admin.settings.public-website.update') }}" novalidate>
    @csrf
    @method('PUT')

    <div class="mb-3.5">
        <label for="footer_text" class="mb-1 block text-xs font-medium text-neutral-700">Footer Text</label>
        <textarea
            id="footer_text"
            name="footer_text"
            rows="3"
            maxlength="500"
            class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('footer_text') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
        >{{ old('footer_text', $settings->get('public.footer_text')) }}</textarea>
        @error('footer_text')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <p class="mb-3.5 text-[11px] text-neutral-400">
        Stored only — no public page currently renders this footer text yet.
    </p>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
