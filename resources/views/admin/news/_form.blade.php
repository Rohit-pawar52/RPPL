{{-- Shared by create.blade.php and edit.blade.php. $news is null on create. --}}
@php
    $news = $news ?? null;
    $maxImages = \App\Models\News::MAX_IMAGES;
    $timezone = app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone');
@endphp

<x-form.input name="title" label="Heading" :value="$news->title ?? ''" maxlength="255" required autofocus />

<x-form.textarea name="content" label="News text" :value="$news->content ?? ''" rows="8" maxlength="20000" required help="Plain text only. Line breaks are kept; HTML is shown as typed, not rendered." />

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-slate-700">Images <span class="font-normal text-slate-400">(optional)</span></p>

    @if($news && $news->images->isNotEmpty())
        <div class="mb-2 flex flex-wrap gap-2">
            @foreach($news->images as $image)
                <label class="block w-24 cursor-pointer">
                    <span class="flex h-16 w-24 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                        <x-media-image :path="$image->image_path" kind="image" :alt="$news->title" class="h-full w-full object-cover" />
                    </span>
                    <span class="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
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
        <p class="mb-2 text-[11px] text-slate-400">Tick Remove on any image to delete it when you save. The first image is used as the cover.</p>
    @endif

    <x-form.image-upload
        name="images[]"
        multiple
        kind="image"
        :empty-text="$news ? 'Click + to add more pictures' : 'Click + to choose pictures'"
        :help="'JPG, PNG or WebP, up to 5 MB each, at most '.$maxImages.' images per post.'"
        :max-bytes="5 * 1024 * 1024"
        too-large="That picture is larger than 5 MB - please choose a smaller one."
    />
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
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Only Active posts appear on the public website. Lower priority numbers are shown first, then the newest.
</p>

<x-form.input
    name="published_at"
    label="Published at"
    type="datetime-local"
    :value="$news?->published_at ? display_datetime($news->published_at, 'Y-m-d\TH:i') : ''"
/>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Times are in the display timezone ({{ $timezone }}). Leave blank to publish now (on create) or keep the current time (on edit). A future time keeps the post hidden until then.
</p>
