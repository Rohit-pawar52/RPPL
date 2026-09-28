@extends('layouts.public')

@section('title', 'Rules & Regulations · '.$branding->shortName)

{{--
    Public Rules & Regulations. Category tabs are plain links carrying
    ?type=<slug> (full page load) so the URL is the source of truth:
    deep links and refreshes keep the selected category, no JS needed.
    See RuleController::index() for which types/rules are visible.
--}}
@section('content')
    <div class="mb-3 flex items-center gap-2">
        <span class="theme-primary-soft-bg theme-primary-text flex h-8 w-8 shrink-0 items-center justify-center rounded-md">
            <x-icon name="book" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <h1 class="text-base font-semibold text-neutral-900">Rules &amp; Regulations</h1>
            <p class="truncate text-[11px] text-neutral-500">{{ $branding->applicationName }}</p>
        </div>
    </div>

    @if($activeTypes->isEmpty())
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <p class="py-4 text-center text-xs text-neutral-400">Rules &amp; Regulations will be published here soon.</p>
        </div>
    @else
        <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs leading-relaxed text-neutral-600" role="note">
            <span class="font-semibold text-neutral-800">Committee decision:</span>
            In any critical, exceptional, disputed, or unforeseen situation not clearly covered by these rules, the decision of the RPPL Committee shall be final.
        </div>

        <nav class="-mx-4 mb-4 overflow-x-auto border-b border-neutral-200 px-4 sm:mx-0 sm:px-0" aria-label="Rule categories">
            <ul class="flex min-w-max items-center gap-1">
                @foreach($activeTypes as $type)
                    @php $current = $type->id === $selectedType->id; @endphp
                    <li>
                        <a
                            href="{{ route('public.rules.index', ['type' => $type->slug]) }}"
                            @if($current) aria-current="page" @endif
                            class="-mb-px inline-flex items-center border-b-2 px-3 py-2.5 text-[13px] font-medium {{ $current ? 'theme-primary-border theme-primary-text' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}"
                        >
                            {{ $type->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <section class="rounded-lg border border-neutral-200 bg-white">
            <div class="border-b border-neutral-100 px-3 py-2.5 sm:px-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ $selectedType->name }}</p>
                @if($selectedType->description)
                    <p class="mt-0.5 text-xs text-neutral-500">{{ $selectedType->description }}</p>
                @endif
            </div>

            <ol class="divide-y divide-neutral-100">
                @foreach($rules as $rule)
                    <li class="relative flex gap-3 px-3 py-3 sm:px-4">
                        @if($rule->is_important)
                            {{-- Separate bar rather than a border-l on the <li>:
                                 divide-* also sets border-color on these rows. --}}
                            <span class="theme-primary-bg absolute inset-y-0 left-0 w-0.5" aria-hidden="true"></span>
                        @endif
                        <span class="w-5 shrink-0 pt-px text-right text-[13px] font-semibold tabular-nums text-neutral-400">{{ $loop->iteration }}.</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h2 class="text-sm font-semibold text-neutral-900 break-words">{{ $rule->title }}</h2>
                                @if($rule->is_important)
                                    <span class="theme-primary-soft-bg theme-primary-text inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium">
                                        <x-icon name="star" class="h-3 w-3" />
                                        Important
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 whitespace-pre-line break-words text-[13px] leading-relaxed text-neutral-700">{{ $rule->content }}</p>
                            @if($rule->image_path)
                                <img
                                    src="{{ Illuminate\Support\Facades\Storage::url($rule->image_path) }}"
                                    alt="{{ $rule->title }}"
                                    loading="lazy"
                                    class="mt-2 h-auto w-full max-w-md rounded-md border border-neutral-200"
                                />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
@endsection
