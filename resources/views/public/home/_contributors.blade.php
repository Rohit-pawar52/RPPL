{{--
    Contributors slider, just above the footer: a swipeable row of up to 20 people (picture, name, village), then
    a "View more" card and link when there are more. Each card shows what that person gave this
    season. A person without a picture gets the default one (<x-media-image>); one without a village shows the
    name alone. Expects $contributors (already limited), $contributorsTotal and $edition.
--}}
@if(! empty($contributors))
    <section aria-label="{{ __('ux_public_contributors.title') }}">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">
                    <x-icon name="star" class="h-5 w-5 text-brand" /> {{ __('ux_public_contributors.title') }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('ux_public_contributors.thanks', ['name' => $branding->shortName]) }}</p>
            </div>
            @if($contributorsTotal > count($contributors))
                <a href="{{ route('public.contributors.index', ['edition_id' => $edition->id]) }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-link hover:text-link-hover">
                    {{ __('ux_public_contributors.view_more') }} <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            @endif
        </div>

        <div class="rppl-strip -mx-4 flex snap-x scroll-px-4 items-stretch gap-3 overflow-x-auto px-4 pb-2 pt-0.5 lg:mx-0 lg:scroll-px-0 lg:px-0">
            @foreach($contributors as $row)
                <div class="pub-card flex w-36 shrink-0 snap-start flex-col items-center gap-2 p-3 text-center sm:w-40">
                    <span class="relative block h-16 w-16 overflow-hidden rounded-full border border-line bg-white">
                        <x-media-image :path="$row['photo_path']" kind="user" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                    </span>
                    <span class="block w-full min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        @if($row['village'])
                            <span class="mt-0.5 block truncate text-xs text-slate-500" title="{{ $row['village'] }}">{{ $row['village'] }}</span>
                        @endif
                        <span class="mt-1.5 inline-block rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-semibold text-brand">{{ money($row['total_amount']) }}</span>
                    </span>
                </div>
            @endforeach

            @if($contributorsTotal > count($contributors))
                <a
                    href="{{ route('public.contributors.index', ['edition_id' => $edition->id]) }}"
                    class="flex w-36 shrink-0 snap-start flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white/60 p-3 text-center text-sm font-semibold text-link transition hover:border-brand hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:w-40"
                >
                    <x-icon name="arrow-right" class="h-5 w-5" />
                    {{ __('ux_public_contributors.view_more') }}
                    <span class="text-xs font-normal text-slate-500">{{ __('ux_public_contributors.more_count', ['count' => $contributorsTotal - count($contributors)]) }}</span>
                </a>
            @endif
        </div>
    </section>
@endif
