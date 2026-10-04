{{--
    An image picker that is a picture, not a "Choose file" button: a box that
    shows the picture already saved (or the default picture when there is
    none), and clicking it opens the file chooser. The chosen file replaces
    the picture at once, so the admin sees what will be saved. Used wherever a
    picture is uploaded, in the admin panel and on the public form.

        <x-form.image-upload name="photo" label="Photo" :current="$player?->photo_path" kind="user" shape="circle" accept="image/png,image/jpeg,image/webp" />

    Props
      name        the input name (with `multiple`, e.g. images[])
      current     the stored path (public disk) of the saved picture, or null
      currentUrl  a ready URL instead of a path (private or signed routes)
      kind        "image" (default.png) or "user" (default-user.jpeg) - the
                  picture shown while there is none
      shape       circle | square | wide - the box; boxClass overrides it
      accept      the accept attribute; video/* types also preview a clip
      required    adds * and the browser's required check
      help        a line under the box
      emptyText   what to tap when nothing is saved or chosen
      removeName  the name of a checkbox that removes the saved picture
      multiple    several pictures: a "+" box, and each chosen file as a
                  thumbnail with a button to drop it
      maxBytes / tooLarge   refuse a bigger file right away, with this message
      stack       true to centre the box above its text (avatars)
--}}
@props([
    'name',
    'label' => null,
    'current' => null,
    'currentUrl' => null,
    'kind' => 'image',
    'shape' => 'square',
    'boxClass' => null,
    'accept' => 'image/png,image/jpeg,image/webp',
    'required' => false,
    'help' => null,
    'emptyText' => 'Click the picture to choose one',
    'changeText' => 'Click the picture to change it',
    'removeName' => null,
    'removeLabel' => 'Remove',
    'multiple' => false,
    'maxBytes' => null,
    'tooLarge' => null,
    'stack' => false,
])

@php
    // The id is the field's name, so a link to the field (the error summary) lands on it.
    $id = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $errorKey = rtrim($name, '[]');
    $hasError = $errors->has($errorKey) || $errors->has($errorKey.'.*');
    $savedUrl = $currentUrl ?: \App\Support\Media::existingUrl($current);
    $hasSaved = (bool) $savedUrl;
    $defaultUrl = \App\Support\Media::defaultUrl($kind);
    $box = $boxClass ?: match ($shape) {
        'circle' => 'h-24 w-24 rounded-full',
        'wide' => 'h-20 w-32 rounded-xl',
        default => 'h-24 w-24 rounded-xl',
    };
    $isVideoSaved = $hasSaved && preg_match('/\.(mp4|webm)(\?|$)/i', $savedUrl);
@endphp

<div
    class="mb-3.5"
    data-image-upload
    data-default-src="{{ $defaultUrl }}"
    data-has-saved="{{ $hasSaved ? '1' : '0' }}"
    data-empty-text="{{ $emptyText }}"
    data-change-text="{{ $changeText }}"
    @if($maxBytes) data-max-bytes="{{ $maxBytes }}" data-too-large="{{ $tooLarge }}" @endif
>
    @if($label)
        <p class="mb-1.5 text-xs font-medium text-slate-700">{{ $label }}@if($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif</p>
    @endif

    <div class="flex {{ $stack ? 'flex-col items-center text-center' : ($multiple ? 'flex-col items-start' : 'items-center') }} gap-3">
        @if($multiple)
            {{-- Several pictures: the chosen ones as thumbnails, and a box to add more. --}}
            <div class="flex flex-wrap gap-2" data-thumbs>
                <label
                    for="{{ $id }}"
                    class="group relative flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-xl border-2 border-dashed bg-slate-50/70 text-slate-400 transition hover:border-green-500 hover:bg-green-50/40 hover:text-green-700 focus-within:border-green-600 focus-within:ring-4 focus-within:ring-green-600/15 {{ $hasError ? 'border-red-400' : 'border-slate-300' }}"
                    data-add-box
                >
                    <input
                        id="{{ $id }}"
                        name="{{ $name }}"
                        type="file"
                        multiple
                        accept="{{ $accept }}"
                        class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                        @required($required)
                        @if($hasError) aria-invalid="true" @endif
                    />
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    <span class="text-[10px] font-semibold uppercase tracking-wide">Add</span>
                </label>
            </div>
        @else
            <label
                for="{{ $id }}"
                class="group relative block shrink-0 cursor-pointer overflow-hidden border-2 bg-slate-50 transition focus-within:ring-4 focus-within:ring-green-600/15 {{ $box }} {{ $hasError ? 'border-red-400' : ($hasSaved ? 'border-slate-200 hover:border-green-500' : 'border-dashed border-slate-300 hover:border-green-500') }}"
                data-box
            >
                <input
                    id="{{ $id }}"
                    name="{{ $name }}"
                    type="file"
                    accept="{{ $accept }}"
                    class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                    @required($required)
                    @if($hasError) aria-invalid="true" @endif
                />

                <img
                    data-preview
                    src="{{ $isVideoSaved ? $defaultUrl : ($savedUrl ?: $defaultUrl) }}"
                    alt=""
                    data-fallback="{{ $kind }}"
                    class="h-full w-full object-cover {{ $hasSaved ? '' : 'opacity-60' }} {{ $isVideoSaved ? 'hidden' : '' }}"
                />
                <video
                    data-preview-video
                    @if($isVideoSaved) src="{{ $savedUrl }}#t=0.1" @endif
                    muted
                    playsinline
                    preload="metadata"
                    class="h-full w-full object-cover {{ $isVideoSaved ? '' : 'hidden' }}"
                ></video>

                {{-- A small camera badge so the box reads as clickable, also on a phone. --}}
                <span class="pointer-events-none absolute bottom-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900/70 text-white shadow transition group-hover:bg-green-600" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A2.5 2.5 0 015.5 6h1.2a1 1 0 00.8-.4l.9-1.2A1 1 0 019 4h6a1 1 0 01.8.4l.9 1.2a1 1 0 00.8.4h1A2.5 2.5 0 0120 8.5v8A2.5 2.5 0 0117.5 19h-11A2.5 2.5 0 014 16.5v-8z" /><circle cx="12" cy="12.5" r="3.5" /></svg>
                </span>
            </label>
        @endif

        <div class="min-w-0">
            <p data-hint class="text-[12px] font-medium text-green-700">{{ $multiple ? $emptyText : ($hasSaved ? $changeText : $emptyText) }}</p>
            <p data-file-name class="max-w-[14rem] truncate text-[11px] text-slate-500"></p>
            @if($help)
                <p class="mt-0.5 text-[11px] text-slate-400">{{ $help }}</p>
            @endif
            @if($removeName && $hasSaved)
                <label class="mt-1.5 flex items-center gap-1.5 text-[11px] text-red-600 {{ $stack ? 'justify-center' : '' }}">
                    <input type="checkbox" name="{{ $removeName }}" value="1" class="rounded border-slate-300" data-remove @checked(old($removeName)) />
                    {{ $removeLabel }}
                </label>
            @endif
        </div>
    </div>

    <p class="mt-1 text-xs font-medium text-red-600" data-upload-note></p>
    @error($errorKey)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
    @foreach($errors->get($errorKey.'.*') as $messages)
        @foreach($messages as $message)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @endforeach
    @endforeach
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-image-upload]').forEach(function (box) {
                var input = box.querySelector('input[type="file"]');
                var hint = box.querySelector('[data-hint]');
                var fileName = box.querySelector('[data-file-name]');
                var note = box.querySelector('[data-upload-note]');
                var maxBytes = parseInt(box.dataset.maxBytes || '0', 10);
                var hasSaved = box.dataset.hasSaved === '1';
                var remove = box.querySelector('[data-remove]');
                var urls = [];

                var isVideo = function (file) { return file.type.indexOf('video/') === 0; };
                var tooBig = function (file) { return maxBytes > 0 && file.size > maxBytes; };

                // --- one picture ---
                if (!input.multiple) {
                    var img = box.querySelector('[data-preview]');
                    var video = box.querySelector('[data-preview-video]');
                    var frame = box.querySelector('[data-box]');
                    var savedSrc = img.getAttribute('src');
                    var savedVideo = video.getAttribute('src');

                    var show = function (src, asVideo, saved) {
                        img.classList.toggle('hidden', asVideo);
                        video.classList.toggle('hidden', !asVideo);
                        if (asVideo) { video.src = src; } else { img.src = src; img.removeAttribute('data-fallback-applied'); }
                        img.classList.toggle('opacity-60', !saved);
                        frame.classList.toggle('border-dashed', !saved);
                    };

                    input.addEventListener('change', function () {
                        var file = input.files && input.files[0];
                        if (note) { note.textContent = ''; }

                        if (urls.length) { URL.revokeObjectURL(urls.pop()); }

                        if (!file) {
                            fileName.textContent = '';
                            hint.textContent = hasSaved ? box.dataset.changeText : box.dataset.emptyText;
                            show(savedVideo ? savedVideo : savedSrc, !!savedVideo, hasSaved);
                            return;
                        }

                        if (tooBig(file)) {
                            input.value = '';
                            if (note) { note.textContent = box.dataset.tooLarge; }
                            return;
                        }

                        var url = URL.createObjectURL(file);
                        urls.push(url);
                        show(isVideo(file) ? url + '#t=0.1' : url, isVideo(file), true);
                        fileName.textContent = file.name;
                        hint.textContent = box.dataset.changeText;
                    });

                    // Ticking "remove" shows the default picture again.
                    if (remove) {
                        remove.addEventListener('change', function () {
                            if (remove.checked) {
                                show(box.dataset.defaultSrc, false, false);
                            } else {
                                var file = input.files && input.files[0];
                                show(file ? urls[urls.length - 1] : (savedVideo ? savedVideo : savedSrc), !file && !!savedVideo, true);
                            }
                        });
                    }

                    return;
                }

                // --- several pictures: keep a list so more can be added one by one ---
                var thumbs = box.querySelector('[data-thumbs]');
                var addBox = box.querySelector('[data-add-box]');
                var store = new DataTransfer();

                var redraw = function () {
                    thumbs.querySelectorAll('[data-thumb]').forEach(function (node) { node.remove(); });
                    urls.forEach(function (url) { URL.revokeObjectURL(url); });
                    urls = [];

                    Array.prototype.forEach.call(store.files, function (file, index) {
                        var url = URL.createObjectURL(file);
                        urls.push(url);

                        var item = document.createElement('div');
                        item.setAttribute('data-thumb', '');
                        item.className = 'relative h-20 w-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-50';
                        item.innerHTML = '<img alt="" class="h-full w-full object-cover" /><button type="button" class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-slate-900/70 text-xs leading-none text-white hover:bg-red-600" aria-label="Remove this picture">&times;</button>';
                        item.querySelector('img').src = url;
                        item.querySelector('button').addEventListener('click', function () {
                            var next = new DataTransfer();
                            Array.prototype.forEach.call(store.files, function (kept, i) { if (i !== index) { next.items.add(kept); } });
                            store = next;
                            input.files = store.files;
                            redraw();
                        });
                        thumbs.insertBefore(item, addBox);
                    });

                    fileName.textContent = store.files.length ? store.files.length + (store.files.length === 1 ? ' picture' : ' pictures') + ' chosen' : '';
                };

                input.addEventListener('change', function () {
                    if (note) { note.textContent = ''; }

                    Array.prototype.forEach.call(input.files, function (file) {
                        if (tooBig(file)) {
                            if (note) { note.textContent = box.dataset.tooLarge; }
                        } else {
                            store.items.add(file);
                        }
                    });

                    input.files = store.files;
                    redraw();
                });
            });
        });
    </script>
@endonce
