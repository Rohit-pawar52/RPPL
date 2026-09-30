{{-- Shared by create.blade.php and edit.blade.php. $news is null on create. --}}
@php
    $news = $news ?? null;
    $maxImages = \App\Models\News::MAX_IMAGES;
    $timezone = app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone');
@endphp

<x-form.input name="title" label="Heading" :value="$news->title ?? ''" maxlength="255" required autofocus />

<div class="mb-3.5">
    <label for="content" class="mb-1 block text-xs font-medium text-neutral-700">News text</label>
    <textarea
        id="content"
        name="content"
        rows="8"
        maxlength="20000"
        required
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('content') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
    >{{ old('content', $news->content ?? '') }}</textarea>
    <p class="mt-1 text-[11px] text-neutral-400">Plain text only. Line breaks are kept; HTML is shown as typed, not rendered.</p>
    @error('content')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-neutral-700">Images <span class="font-normal text-neutral-400">(optional)</span></p>

    @if($news && $news->images->isNotEmpty())
        <div class="mb-2 flex flex-wrap gap-2">
            @foreach($news->images as $image)
                <label class="block w-24 cursor-pointer">
                    <span class="flex h-16 w-24 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50">
                        <img
                            src="{{ Illuminate\Support\Facades\Storage::url($image->image_path) }}"
                            alt="{{ $news->title }}"
                            class="h-full w-full object-cover"
                        />
                    </span>
                    <span class="mt-1 flex items-center gap-1 text-[11px] text-neutral-500">
                        <input
                            type="checkbox"
                            name="remove_images[]"
                            value="{{ $image->id }}"
                            {{ in_array($image->id, array_map('intval', (array) old('remove_images', []))) ? 'checked' : '' }}
                        />
                        Remove
                    </span>
                </label>
            @endforeach
        </div>
        <p class="mb-2 text-[11px] text-neutral-400">Tick Remove on any image to delete it when you save. The first image is used as the cover.</p>
    @endif

    <div class="rounded-md border border-dashed px-3 py-3 {{ $errors->has('images') || $errors->has('images.*') ? 'border-red-400' : 'border-neutral-300' }}">
        <label class="cursor-pointer text-[12px] font-medium theme-link">
            {{ $news ? 'Add images' : 'Choose images' }}
            <input
                type="file"
                name="images[]"
                multiple
                accept="image/png,image/jpeg,image/webp"
                class="hidden"
                onchange="document.getElementById('news-images-count').textContent = this.files.length ? this.files.length + ' file(s) selected' : ''"
            />
        </label>
        <p id="news-images-count" class="mt-1 text-[11px] text-neutral-500"></p>
    </div>
    <p class="mt-1 text-[11px] text-neutral-400">JPG, PNG or WebP, up to 5 MB each, at most {{ $maxImages }} images per post.</p>
    @error('images')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    @foreach($errors->get('images.*') as $messages)
        @foreach($messages as $message)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @endforeach
    @endforeach
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="status"
        label="Status"
        :options="['active' => 'Active', 'inactive' => 'Inactive']"
        :value="$news->status ?? 'active'"
    />
    <x-form.input
        name="priority"
        label="Priority"
        type="number"
        min="1"
        max="9999"
        :value="$news->priority ?? 100"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Only Active posts appear on the public website. Lower priority numbers are shown first, then the newest.
</p>

<x-form.input
    name="published_at"
    label="Published at"
    type="datetime-local"
    :value="$news?->published_at ? display_datetime($news->published_at, 'Y-m-d\TH:i') : ''"
/>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Times are in the display timezone ({{ $timezone }}). Leave blank to publish now (on create) or keep the current time (on edit). A future time keeps the post hidden until then.
</p>
