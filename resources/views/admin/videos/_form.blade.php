{{-- Shared by create.blade.php and edit.blade.php. $video is null on create. --}}
@php
    $video = $video ?? null;
    $maxUploadMb = (int) config('videos.max_upload_mb');
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card title="Video">
            <x-form.image-upload
                name="video"
                label="Video file"
                accept="video/mp4,video/webm"
                :current="$video?->video_path"
                kind="image"
                shape="wide"
                box-class="h-44 w-full max-w-md rounded-xl sm:h-56"
                stack
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
        </x-admin.card>

        <x-admin.card title="Details">
            <x-form.input name="title" label="Title" :value="$video->title ?? ''" maxlength="255" required autofocus />
            <x-form.textarea name="description" label="Description (optional)" :value="$video->description ?? ''" rows="3" maxlength="2000" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Publishing">
            <x-form.select
                name="status"
                label="Status"
                :options="['active' => 'Active', 'inactive' => 'Inactive']"
                :value="$video->status ?? 'active'"
                help="Only Active videos appear on the public website."
            />
            <x-form.input
                name="priority"
                label="Priority"
                type="number"
                min="1"
                max="9999"
                :value="$video->priority ?? 100"
                help="Lower numbers are shown first."
            />
        </x-admin.card>
    </div>
</div>
