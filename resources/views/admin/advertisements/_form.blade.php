{{-- Shared by create.blade.php and edit.blade.php. $advertisement is null on create. --}}
@php
    $advertisement = $advertisement ?? null;
    $maxImageMb = (int) config('ads.max_image_mb');
    $maxVideoMb = (int) config('ads.max_video_mb');
    $spotKey = $advertisement?->spotKey() ?? 'normal-banner';
@endphp

<div class="crud-grid crud-grid-wide">
    <div class="crud-main">
        <x-admin.card title="Where it appears">
            <x-form.input name="title" label="Title" :value="$advertisement->title ?? ''" maxlength="255" required autofocus />

            <x-form.select
                name="tier"
                label="Sponsor level"
                :options="\App\Models\Advertisement::TIERS"
                :value="$advertisement->tier ?? 'normal'"
            />
            <ul class="-mt-2 mb-3.5 space-y-1 text-xs text-slate-500">
                <li><span class="font-semibold text-slate-700">Main</span> — the top banner, always shown first (only one Main sponsor at a time).</li>
                <li><span class="font-semibold text-slate-700">Auction</span> — the pop-up on the player auction page (only one at a time; until one is added, the Main sponsor is shown there).</li>
                <li><span class="font-semibold text-slate-700">Normal</span> — takes turns with the other Normal sponsors of the same spot on every page load.</li>
                <li><span class="font-semibold text-slate-700">Mini</span> — a small logo in the "Our sponsors" strip at the bottom (image only).</li>
            </ul>

            <div data-format-field class="mt-3.5">
                <x-form.select
                    name="format"
                    label="Normal sponsor spot"
                    :options="\App\Models\Advertisement::FORMATS"
                    :value="$advertisement?->effectiveFormat() ?? 'banner'"
                />
            </div>
        </x-admin.card>

        <x-admin.card title="The picture or clip">
            <x-form.select
                name="media_type"
                label="Type"
                :options="['image' => 'Image', 'video' => 'Video']"
                :value="$advertisement->media_type ?? 'image'"
            />

            <x-form.image-upload
                name="media"
                label="Image or video"
                accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                :current="$advertisement?->media_path"
                kind="image"
                shape="wide"
                box-class="h-40 w-full max-w-md rounded-xl sm:h-52"
                stack
                empty-text="Click the box to choose a picture or a clip"
                change-text="Click the box to change it"
                :help="'Image: JPG, PNG or WebP up to '.$maxImageMb.' MB. Video: MP4 or WebM up to '.$maxVideoMb.' MB, plays muted on a loop.'.($advertisement ? ' Leave it alone to keep the current file.' : '')"
            />

            <p class="crud-note crud-note-brand mb-4">
                Best picture size for the chosen spot: <span id="spot-size-hint" class="font-semibold text-brand">{{ \App\Models\Advertisement::SPOTS[$spotKey]['size'] }} (ratio {{ \App\Models\Advertisement::SPOTS[$spotKey]['ratio'] }})</span>
            </p>

            <x-form.image-upload
                name="poster"
                label="Preview picture (video only, optional)"
                accept="image/jpeg,image/png,image/webp"
                :current="$advertisement?->poster_path"
                kind="image"
                shape="wide"
                empty-text="Click the box to choose a preview picture"
                help="Shown while the video loads. JPG, PNG or WebP up to 2 MB."
            />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Schedule">
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
                help="Matters for Normal sponsors that take turns: a 3 is shown about three times as often as a 1."
            />
            <div class="crud-cols">
                <x-form.input
                    name="starts_on"
                    label="Show from"
                    type="date"
                    :value="$advertisement?->starts_on?->format('Y-m-d') ?? ''"
                />
                <x-form.input
                    name="ends_on"
                    label="Show until"
                    type="date"
                    :value="$advertisement?->ends_on?->format('Y-m-d') ?? ''"
                />
            </div>
            <p class="crud-note">Both dates are optional. Only Active ads inside their dates appear on the public website.</p>
        </x-admin.card>

        @include('admin.advertisements._size-guide', ['highlight' => $spotKey])
    </div>
</div>

{{-- Highlights the picture-size row for the spot chosen above, shows its
     size next to the file picker, and hides the "Normal sponsor spot" choice
     for the other levels. Without JavaScript every row simply stays visible. --}}
<script>
    (function () {
        var tier = document.getElementById('tier');
        var format = document.getElementById('format');
        var formatField = document.querySelector('[data-format-field]');
        var rows = document.querySelectorAll('[data-spot]');
        var hint = document.getElementById('spot-size-hint');
        if (!tier || !format) { return; }

        var refresh = function () {
            var spot = tier.value === 'normal' ? 'normal-' + format.value : tier.value;
            formatField.hidden = tier.value !== 'normal';
            rows.forEach(function (row) {
                var on = row.dataset.spot === spot;
                row.classList.toggle('bg-brand-soft', on);
                if (on && hint) {
                    hint.textContent = row.dataset.size + ' (ratio ' + row.dataset.ratio + ')';
                }
            });
        };

        tier.addEventListener('change', refresh);
        format.addEventListener('change', refresh);
        refresh();
    })();
</script>
