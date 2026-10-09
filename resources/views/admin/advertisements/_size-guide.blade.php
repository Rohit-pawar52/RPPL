{{--
    Which picture size fits which spot (Advertisement::SPOTS). On the form
    the row for the spot currently chosen is highlighted by the script in
    _form.blade.php. Optional $highlight = a SPOTS key to start with.
--}}
@php $highlight = $highlight ?? null; @endphp

<div class="mb-3.5 overflow-hidden rounded-md border border-slate-200 bg-slate-50/60" data-spot-guide>
    <p class="border-b border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">{{ __('Picture size for each spot') }}</p>
    <ul class="divide-y divide-slate-200">
        @foreach(\App\Models\Advertisement::SPOTS as $key => $spot)
            @php
                preg_match_all('/\d+/', $spot['size'], $dimensions);
                [$width, $height] = array_map('intval', array_slice($dimensions[0], 0, 2));
            @endphp
            <li data-spot="{{ $key }}" data-size="{{ $spot['size'] }}" data-ratio="{{ $spot['ratio'] }}" class="px-3 py-2 text-[11px] leading-snug text-slate-500 {{ $highlight === $key ? 'bg-brand-soft' : '' }}">
                <div class="flex items-center gap-3">
                    {{-- A little box drawn in the same proportions. --}}
                    <span class="hidden w-20 shrink-0 sm:block">
                        <span class="block max-h-10 w-full rounded-sm border border-slate-300 bg-slate-200" style="aspect-ratio: {{ $width }} / {{ $height }};"></span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[12px] font-medium text-slate-800">{{ __($spot['label']) }}</p>
                        <p><span class="font-semibold text-slate-700">{{ $spot['size'] }}</span> &middot; {{ __('ratio :ratio', ['ratio' => $spot['ratio']]) }}</p>
                    </div>
                </div>
                <p class="mt-1">{{ __($spot['where']) }} {{ __($spot['note']) }}</p>
            </li>
        @endforeach
    </ul>
    <p class="border-t border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-400">
        {{ __('A picture of a different shape is never cropped — it keeps its proportions and the empty sides are filled with a blurred copy of it. JPG, PNG or WebP up to :image MB; a video up to :video MB (MP4 or WebM, muted, banner and card only).', ['image' => (int) config('ads.max_image_mb'), 'video' => (int) config('ads.max_video_mb')]) }}
    </p>
</div>
