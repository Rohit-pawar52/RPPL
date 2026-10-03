{{-- Shared by create.blade.php and edit.blade.php. $advertisement is null on create. --}}
@php
    $advertisement = $advertisement ?? null;
    $maxImageMb = (int) config('ads.max_image_mb');
    $maxVideoMb = (int) config('ads.max_video_mb');
@endphp

<x-form.input name="title" label="Title" :value="$advertisement->title ?? ''" maxlength="255" required autofocus />

<x-form.select
    name="tier"
    label="Sponsor level"
    :options="\App\Models\Advertisement::TIERS"
    :value="$advertisement->tier ?? 'normal'"
/>
<ul class="-mt-2.5 mb-3.5 space-y-0.5 text-[11px] text-slate-400">
    <li><span class="font-medium text-slate-500">Main</span> — one big banner at the top of the page, always shown (only one Main sponsor at a time).</li>
    <li><span class="font-medium text-slate-500">Normal</span> — a banner below the content; if there are several, they take turns on every page load.</li>
    <li><span class="font-medium text-slate-500">Mini</span> — a small logo in the "Our sponsors" strip at the bottom (image only).</li>
</ul>

<x-form.select
    name="media_type"
    label="Type"
    :options="['image' => 'Image', 'video' => 'Video']"
    :value="$advertisement->media_type ?? 'image'"
/>

<x-form.file
    name="media"
    label="Image or video file"
    accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
    :current="$advertisement?->media_path ? basename($advertisement->media_path) : null"
    :prompt="$advertisement?->media_path ? 'Replace file' : 'Choose file'"
    :help="'Image: JPG, PNG or WebP up to '.$maxImageMb.' MB. Video: MP4 or WebM up to '.$maxVideoMb.' MB, plays muted on a loop. Wide banners work best (Main about 1500×300, Normal about 1200×200); Mini logos are shown small.'.($advertisement ? ' Leave empty to keep the current file.' : '')"
/>

<x-form.file
    name="poster"
    label="Preview image (video only, optional)"
    accept="image/jpeg,image/png,image/webp"
    :current="$advertisement?->poster_path ? basename($advertisement->poster_path) : null"
    :prompt="$advertisement?->poster_path ? 'Replace preview' : 'Choose preview'"
    help="Shown while the video loads. JPG, PNG or WebP up to 2 MB."
/>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="status"
        label="Status"
        :options="['active' => 'Active', 'inactive' => 'Inactive']"
        :value="$advertisement->status ?? 'active'"
    />
    <x-form.input
        name="weight"
        label="How often (1–10)"
        type="number"
        min="1"
        :max="\App\Models\Advertisement::MAX_WEIGHT"
        :value="$advertisement->weight ?? 1"
    />
    <x-form.input
        name="starts_on"
        label="Show from (optional)"
        type="date"
        :value="$advertisement?->starts_on?->format('Y-m-d') ?? ''"
    />
    <x-form.input
        name="ends_on"
        label="Show until (optional)"
        type="date"
        :value="$advertisement?->ends_on?->format('Y-m-d') ?? ''"
    />
</div>
<p class="-mt-0.5 mb-3.5 text-[11px] text-slate-400">
    Only Active ads inside their dates appear on the public website. "How often" matters for Normal sponsors that take turns: a 3 is shown about three times as often as a 1.
</p>
