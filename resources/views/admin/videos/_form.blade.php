{{-- Shared by create.blade.php and edit.blade.php. $video is null on create. --}}
@php
    $video = $video ?? null;
    $maxUploadMb = (int) config('videos.max_upload_mb');
@endphp

<x-form.input name="title" label="Title" :value="$video->title ?? ''" maxlength="255" required autofocus />

<div class="mb-3.5">
    <label for="description" class="mb-1 block text-xs font-medium text-neutral-700">Description <span class="font-normal text-neutral-400">(optional)</span></label>
    <textarea
        id="description"
        name="description"
        rows="3"
        maxlength="2000"
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('description') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
    >{{ old('description', $video->description ?? '') }}</textarea>
    @error('description')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-neutral-700">Video file</p>

    <div class="rounded-md border border-dashed px-3 py-3 {{ $errors->has('video') ? 'border-red-400' : 'border-neutral-300' }}">
        @if($video?->video_path)
            <p class="mb-1.5 flex items-center gap-1.5 text-[12px] text-neutral-600">
                <x-icon name="play" class="h-4 w-4 shrink-0 text-neutral-400" />
                <span class="truncate">Current: {{ basename($video->video_path) }}</span>
            </p>
        @endif

        <label class="cursor-pointer text-[12px] font-medium theme-link">
            {{ $video?->video_path ? 'Replace video' : 'Choose video' }}
            <input
                type="file"
                name="video"
                accept="video/mp4,video/webm"
                class="hidden"
                onchange="document.getElementById('video-filename').textContent = this.files[0]?.name ?? ''"
            />
        </label>
        <p id="video-filename" class="mt-1 truncate text-[11px] text-neutral-500"></p>
    </div>

    <p class="mt-1 text-[11px] text-neutral-400">
        MP4 or WebM, up to {{ $maxUploadMb }} MB. Recommended for short RPPL clips (approximately 1–2 minutes).
        @if($video)
            Leave empty to keep the current video.
        @endif
    </p>
    @error('video')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-neutral-700">Thumbnail <span class="font-normal text-neutral-400">(optional)</span></p>

    <div class="flex items-center gap-3">
        <div class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 text-neutral-300">
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
            <label class="cursor-pointer text-[12px] font-medium theme-link">
                {{ $video?->thumbnail_path ? 'Replace thumbnail' : 'Upload thumbnail' }}
                <input
                    type="file"
                    name="thumbnail"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('thumbnail-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="thumbnail-filename" class="mt-1 max-w-[12rem] truncate text-[11px] text-neutral-500"></p>
            <p class="mt-1 text-[11px] text-neutral-400">JPG, PNG or WebP, up to 2 MB.</p>
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
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Only Active videos appear on the public website. Lower priority numbers are shown first.
</p>
