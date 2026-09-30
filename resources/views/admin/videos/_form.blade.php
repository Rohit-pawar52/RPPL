{{-- Shared by create.blade.php and edit.blade.php. $video is null on create. --}}
@php
    $video = $video ?? null;
    $maxUploadMb = (int) config('videos.max_upload_mb');
@endphp

<x-form.input name="title" label="Title" :value="$video->title ?? ''" maxlength="255" required autofocus />

<x-form.textarea name="description" label="Description (optional)" :value="$video->description ?? ''" rows="3" maxlength="2000" />

<x-form.file
    name="video"
    label="Video file"
    accept="video/mp4,video/webm"
    :current="$video?->video_path ? basename($video->video_path) : null"
    :prompt="$video?->video_path ? 'Replace video' : 'Choose video'"
    :help="'MP4 or WebM, up to '.$maxUploadMb.' MB. Recommended for short RPPL clips (approximately 1–2 minutes).'.($video ? ' Leave empty to keep the current video.' : '')"
/>

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-slate-700">Thumbnail <span class="font-normal text-slate-400">(optional)</span></p>

    <div class="flex items-center gap-3">
        <div class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 text-slate-300">
            @if($video?->thumbnail_path)
                <img
                    src="{{ Illuminate\Support\Facades\Storage::url($video->thumbnail_path) }}"
                    alt="{{ $video->title }}"
                    class="h-full w-full object-cover"
                />
            @else
                <x-icon name="play" class="h-6 w-6" />
            @endif
        </div>

        <div class="min-w-0">
            <label class="cursor-pointer text-[12px] font-medium text-green-700">
                {{ $video?->thumbnail_path ? 'Replace thumbnail' : 'Upload thumbnail' }}
                <input
                    type="file"
                    name="thumbnail"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('thumbnail-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="thumbnail-filename" class="mt-1 max-w-[12rem] truncate text-[11px] text-slate-500"></p>
            <p class="mt-1 text-[11px] text-slate-400">JPG, PNG or WebP, up to 2 MB.</p>
        </div>
    </div>

    @error('thumbnail')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

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
