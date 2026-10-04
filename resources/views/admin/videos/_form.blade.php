{{-- Shared by create.blade.php and edit.blade.php. $video is null on create. --}}
@php
    $video = $video ?? null;
    $maxUploadMb = (int) config('videos.max_upload_mb');
@endphp

<x-form.input name="title" label="Title" :value="$video->title ?? ''" maxlength="255" required autofocus />

<x-form.textarea name="description" label="Description (optional)" :value="$video->description ?? ''" rows="3" maxlength="2000" />

<x-form.image-upload
    name="video"
    label="Video file"
    accept="video/mp4,video/webm"
    :current="$video?->video_path"
    kind="image"
    shape="wide"
    empty-text="Click the box to choose a video"
    change-text="Click the box to change the video"
    :help="'MP4 or WebM, up to '.$maxUploadMb.' MB. Recommended for short RPPL clips (approximately 1–2 minutes).'.($video ? ' Leave it alone to keep the current video.' : '')"
/>

<x-form.image-upload
    name="thumbnail"
    label="Thumbnail (optional)"
    :current="$video?->thumbnail_path"
    kind="image"
    shape="wide"
    empty-text="Click the picture to choose a thumbnail"
    help="JPG, PNG or WebP, up to 2 MB."
/>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="status"
        label="Status"
        :options="['active' => 'Active', 'inactive' => 'Inactive']"
        :value="$video->status ?? 'active'"
    />
    <x-form.input
        name="priority"
        label="Priority"
        type="number"
        min="1"
        max="9999"
        :value="$video->priority ?? 100"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Only Active videos appear on the public website. Lower priority numbers are shown first.
</p>
