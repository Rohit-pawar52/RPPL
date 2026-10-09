{{-- Shared by create.blade.php and edit.blade.php. $video is null on create. --}}
@php
    $video = $video ?? null;
    $maxUploadMb = (int) config('videos.max_upload_mb');
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Video')">
            <x-form.image-upload
                name="video"
                :label="__('Video file')"
                accept="video/mp4,video/webm"
                :current="$video?->video_path"
                kind="image"
                shape="wide"
                box-class="h-44 w-full max-w-md rounded-xl sm:h-56"
                stack
                :empty-text="__('Click the box to choose a video')"
                :change-text="__('Click the box to change the video')"
                :help="__('MP4 or WebM, up to :max MB. Recommended for short RPPL clips (approximately 1–2 minutes).', ['max' => $maxUploadMb]).($video ? ' '.__('Leave it alone to keep the current video.') : '')"
            />

            <x-form.image-upload
                name="thumbnail"
                :label="__('Thumbnail (optional)')"
                :current="$video?->thumbnail_path"
                kind="image"
                shape="wide"
                :empty-text="__('Click the picture to choose a thumbnail')"
                :help="__('JPG, PNG or WebP, up to 2 MB.')"
            />
        </x-admin.card>

        <x-admin.card :title="__('Details')">
            <x-form.input name="title" :label="__('Title')" :value="$video->title ?? ''" maxlength="255" required autofocus />
            <x-form.textarea name="description" :label="__('Description (optional)')" :value="$video->description ?? ''" rows="3" maxlength="2000" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Publishing')">
            <x-form.select
                name="status"
                :label="__('Status')"
                :options="['active' => __('Active'), 'inactive' => __('Inactive')]"
                :value="$video->status ?? 'active'"
                :help="__('Only Active videos appear on the public website.')"
            />
            <x-form.input
                name="priority"
                :label="__('Priority')"
                type="number"
                min="1"
                max="9999"
                :value="$video->priority ?? 100"
                :help="__('Lower numbers are shown first.')"
            />
        </x-admin.card>
    </div>
</div>
