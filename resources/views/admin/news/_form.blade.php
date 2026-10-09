{{-- Shared by create.blade.php and edit.blade.php. $news is null on create. --}}
@php
    $news = $news ?? null;
    $maxImages = \App\Models\News::MAX_IMAGES;
    $timezone = app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone');
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('The post')">
            <x-form.input name="title" :label="__('Heading')" :value="$news->title ?? ''" maxlength="255" required autofocus />

            <x-form.textarea name="content" :label="__('News text')" :value="$news->content ?? ''" rows="10" maxlength="20000" required :help="__('Plain text only. Line breaks are kept; HTML is shown as typed, not rendered.')" />
        </x-admin.card>

        <x-admin.card :title="__('Images (optional)')">
            @if($news && $news->images->isNotEmpty())
                <div class="mb-3 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                    @foreach($news->images as $image)
                        <label class="block cursor-pointer text-center">
                            <x-crud.thumb :path="$image->image_path" kind="image" shape="square" class="h-auto! aspect-square w-full!" :alt="$news->title" />
                            <span class="mt-1.5 flex items-center justify-center gap-1.5 text-[11px] text-slate-500">
                                <input
                                    type="checkbox"
                                    name="remove_images[]"
                                    value="{{ $image->id }}"
                                    class="rounded border-slate-300 text-red-600 focus:ring-red-200"
                                    {{ in_array($image->id, array_map('intval', (array) old('remove_images', []))) ? 'checked' : '' }}
                                />
                                {{ __('Remove') }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="crud-note mb-3">{{ __('Tick Remove on any image to delete it when you save. The first image is used as the cover.') }}</p>
            @endif

            <x-form.image-upload
                name="images[]"
                multiple
                kind="image"
                :empty-text="$news ? __('Click + to add more pictures') : __('Click + to choose pictures')"
                :help="__('JPG, PNG or WebP, up to 5 MB each, at most :max images per post.', ['max' => $maxImages])"
                :max-bytes="5 * 1024 * 1024"
                :too-large="__('That picture is larger than 5 MB - please choose a smaller one.')"
            />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Publishing')">
            <x-form.select
                name="status"
                :label="__('Status')"
                :options="['active' => __('Active'), 'inactive' => __('Inactive')]"
                :value="$news->status ?? 'active'"
                :help="__('Only Active posts appear on the public website.')"
            />
            <x-form.input
                name="priority"
                :label="__('Priority')"
                type="number"
                min="1"
                max="9999"
                :value="$news->priority ?? 100"
                :help="__('Lower numbers are shown first, then the newest.')"
            />
            <x-form.input
                name="published_at"
                :label="__('Published at')"
                type="datetime-local"
                :value="$news?->published_at ? display_datetime($news->published_at, 'Y-m-d\TH:i') : ''"
                :help="__('Times are in the display timezone (:timezone). Leave blank to publish now (on create) or keep the current time (on edit). A future time keeps the post hidden until then.', ['timezone' => $timezone])"
            />
        </x-admin.card>
    </div>
</div>
