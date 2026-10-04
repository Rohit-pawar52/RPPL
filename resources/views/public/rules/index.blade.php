@extends('layouts.public')

@section('title', __('directory.rules.title').' · '.$branding->shortName)

{{--
    Public Rules & Regulations. Category tabs are plain links carrying
    ?type=<slug> (full page load) so the URL is the source of truth:
    deep links and refreshes keep the selected category, no JS needed.
    See RuleController::index() for which types/rules are visible.
--}}
@section('content')
    <x-public.page-header :title="__('directory.rules.title')" :subtitle="$branding->applicationName" />

    @if($activeTypes->isEmpty())
        <x-public.card>
            <x-public.empty>{{ __('directory.rules.empty') }}</x-public.empty>
        </x-public.card>
    @else
        <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800 ring-1 ring-inset ring-amber-200" role="note">
            <span class="font-semibold">{{ __('directory.rules.committee_label') }}</span>
            {{ __('directory.rules.committee_notice') }}
        </div>

        <nav class="pub-tabs mb-4" aria-label="{{ __('directory.rules.categories') }}">
            @foreach($activeTypes as $type)
                @php $current = $type->id === $selectedType->id; @endphp
                <a
                    href="{{ route('public.rules.index', ['type' => $type->slug]) }}"
                    @if($current) aria-current="page" @endif
                    class="pub-tab {{ $current ? 'pub-tab-active' : '' }}"
                >
                    {{ $type->name }}
                </a>
            @endforeach
        </nav>

        <section class="pub-card overflow-hidden">
            <div class="border-b border-line bg-slate-50 px-4 py-3">
                <h2 class="pub-h2">{{ $selectedType->name }}</h2>
                @if($selectedType->description)
                    <p class="pub-meta mt-0.5">{{ $selectedType->description }}</p>
                @endif
            </div>

            <ol class="divide-y divide-line">
                @foreach($rules as $rule)
                    <li class="relative flex gap-3 px-4 py-4">
                        @if($rule->is_important)
                            {{-- Separate bar rather than a border-l on the <li>:
                                 divide-* also sets border-color on these rows. --}}
                            <span class="absolute inset-y-0 left-0 w-1 bg-green-600" aria-hidden="true"></span>
                        @endif
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold tabular-nums text-slate-500">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h3 class="break-words text-sm font-semibold text-slate-900">{{ $rule->title }}</h3>
                                @if($rule->is_important)
                                    <span class="pub-pill pub-pill-success !normal-case">
                                        <x-icon name="star" class="h-3 w-3" />
                                        {{ __('directory.rules.important') }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1.5 max-w-[75ch] whitespace-pre-line break-words text-[14px] leading-relaxed text-slate-700">{{ $rule->content }}</p>
                            @if($rule->image_path)
                                <div class="pub-media mt-3 max-w-md rounded-lg border border-line">
                                    <x-media-image :path="$rule->image_path" kind="image" :alt="$rule->title" loading="lazy" class="h-auto w-full" />
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
@endsection
