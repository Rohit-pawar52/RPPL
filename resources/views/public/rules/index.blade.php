@extends('layouts.public')

@section('title', __('directory.rules.title').' · '.$branding->shortName)

{{--
    Public Rules & Regulations. Category tabs are plain links carrying
    ?type=<slug> (full page load) so the URL is the source of truth:
    deep links and refreshes keep the selected category, no JS needed.
    See RuleController::index() for which types/rules are visible.

    Inside a category: an index of its rules that follows the reader (a
    sticky side list on a large screen, a sticky strip of chips on a phone),
    a quick filter (type a word, or show only the important rules), and an
    anchor link on every rule so a rule can be shared.
--}}
@section('content')
    <x-public.page-header :title="__('directory.rules.title')" :subtitle="$branding->applicationName" />

    @if($activeTypes->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="book" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('directory.rules.empty') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.rules.empty_hint') }}</p>
            <a href="{{ route('public.home') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.news.go_home') }}</a>
        </div>
    @else
        @php $importantCount = $rules->where('is_important', true)->count(); @endphp

        <div class="mb-4 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900 ring-1 ring-inset ring-amber-200" role="note">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <p>
                <span class="font-semibold">{{ __('directory.rules.committee_label') }}</span>
                {{ __('directory.rules.committee_notice') }}
            </p>
        </div>

        <nav class="pc-chips mb-4" aria-label="{{ __('directory.rules.categories') }}">
            @foreach($activeTypes as $type)
                @php $current = $type->id === $selectedType->id; @endphp
                <a
                    href="{{ route('public.rules.index', ['type' => $type->slug]) }}"
                    @if($current) aria-current="page" @endif
                    class="pc-chip {{ $current ? 'pc-chip-active' : '' }}"
                >
                    {{ $type->name }}
                    <span class="rounded-full bg-black/5 px-1.5 text-[10px] font-bold tabular-nums">{{ $type->rules->count() }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Phones: the rules as a strip of chips that stays under the header. --}}
        <nav class="sticky top-14 z-30 -mx-4 mb-4 border-b border-line bg-surface/95 px-4 py-2 backdrop-blur lg:hidden" aria-label="{{ __('ux_public_content.rules.in_this_category') }}">
            <div class="pc-chips" data-rules-strip>
                @foreach($rules as $rule)
                    <a href="#rule-{{ $rule->id }}" class="pc-chip max-w-[14rem]" data-rule-link="rule-{{ $rule->id }}">
                        <span class="font-bold tabular-nums">{{ $loop->iteration }}</span>
                        <span class="truncate">{{ $rule->title }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="lg:grid lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start lg:gap-8">
            {{-- Large screens: the index follows the reader. --}}
            <aside class="sticky top-20 hidden max-h-[calc(100vh-6rem)] overflow-y-auto rounded-xl border border-line bg-white p-2 shadow-card lg:block" aria-label="{{ __('ux_public_content.rules.in_this_category') }}">
                <p class="px-2.5 pb-1.5 pt-2 pc-eyebrow">{{ __('ux_public_content.rules.in_this_category') }}</p>
                <ol class="space-y-0.5">
                    @foreach($rules as $rule)
                        <li>
                            <a href="#rule-{{ $rule->id }}" class="pc-index-link" data-rule-link="rule-{{ $rule->id }}">
                                <span class="w-5 shrink-0 text-right text-xs font-bold tabular-nums text-slate-400">{{ $loop->iteration }}</span>
                                <span class="min-w-0 flex-1 break-words">{{ $rule->title }}</span>
                                @if($rule->is_important)
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand" title="{{ __('directory.rules.important') }}"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            </aside>

            <section aria-labelledby="rules-type-title" data-rules>
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h2 id="rules-type-title" class="text-xl font-bold tracking-tight text-slate-900">{{ $selectedType->name }}</h2>
                        @if($selectedType->description)
                            <p class="mt-0.5 text-sm text-slate-500">{{ $selectedType->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="relative block w-full sm:w-56">
                            <span class="sr-only">{{ __('ux_public_content.rules.filter') }}</span>
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                            <input type="search" data-rules-filter autocomplete="off" placeholder="{{ __('ux_public_content.rules.filter') }}" class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 text-[13px] text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand/15" />
                        </label>
                        @if($importantCount > 0)
                            <button type="button" data-rules-important aria-pressed="false" class="pc-chip shrink-0 aria-pressed:border-brand aria-pressed:bg-brand-soft aria-pressed:text-brand">
                                <x-icon name="star" class="h-3.5 w-3.5" />
                                {{ __('directory.rules.important') }}
                                <span class="tabular-nums">{{ $importantCount }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <ol class="space-y-3">
                    @foreach($rules as $rule)
                        <li
                            id="rule-{{ $rule->id }}"
                            class="pc-rule {{ $rule->is_important ? 'pc-rule-important' : '' }}"
                            data-rule
                            data-important="{{ $rule->is_important ? '1' : '0' }}"
                            data-rule-text="{{ mb_strtolower($rule->title.' '.$rule->content) }}"
                        >
                            <div class="flex gap-3 sm:gap-4">
                                <span class="pc-rule-no">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <h3 class="break-words text-[15px] font-semibold leading-snug text-slate-900">{{ $rule->title }}</h3>
                                        @if($rule->is_important)
                                            <span class="pub-pill bg-brand-soft text-brand !normal-case ring-1 ring-inset ring-brand/20">
                                                <x-icon name="star" class="h-3 w-3" />
                                                {{ __('directory.rules.important') }}
                                            </span>
                                        @endif
                                        <button
                                            type="button"
                                            class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 transition hover:bg-hover hover:text-brand focus-visible:outline-2 focus-visible:outline-brand"
                                            data-copy-link="rule-{{ $rule->id }}"
                                            data-copied="{{ __('ux_public_content.rules.link_copied') }}"
                                            aria-label="{{ __('ux_public_content.rules.copy_link') }}"
                                            title="{{ __('ux_public_content.rules.copy_link') }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1" /><path d="M14 10a4 4 0 00-5.7 0l-3 3A4 4 0 0011 18.7l1-1" /></svg>
                                        </button>
                                    </div>
                                    <p class="mt-2 max-w-[75ch] whitespace-pre-line break-words text-[14px] leading-relaxed text-slate-700">{{ $rule->content }}</p>
                                    @if($rule->image_path)
                                        <div class="pub-media mt-3 max-w-md rounded-lg border border-line">
                                            <x-media-image :path="$rule->image_path" kind="image" :alt="$rule->title" loading="lazy" class="h-auto w-full" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="pc-empty mt-3" data-rules-empty hidden>
                    <span class="pc-empty-icon"><x-icon name="book" class="h-7 w-7" /></span>
                    <p class="pc-empty-title">{{ __('ux_public_content.rules.no_match') }}</p>
                    <button type="button" class="btn btn-secondary mt-2" data-rules-reset>{{ __('ux_public_content.search.clear') }}</button>
                </div>
            </section>
        </div>

        <script>
            (function () {
                var root = document.querySelector('[data-rules]');
                if (! root) { return; }

                var items = Array.prototype.slice.call(root.querySelectorAll('[data-rule]'));
                var filter = root.querySelector('[data-rules-filter]');
                var important = root.querySelector('[data-rules-important]');
                var none = root.querySelector('[data-rules-empty]');
                var links = Array.prototype.slice.call(document.querySelectorAll('[data-rule-link]'));

                var apply = function () {
                    var term = filter.value.trim().toLowerCase();
                    var onlyImportant = important && important.getAttribute('aria-pressed') === 'true';
                    var shown = 0;

                    items.forEach(function (item) {
                        var match = (term === '' || item.dataset.ruleText.indexOf(term) !== -1)
                            && (! onlyImportant || item.dataset.important === '1');
                        item.hidden = ! match;
                        if (match) { shown++; }
                        links.forEach(function (link) {
                            if (link.dataset.ruleLink === item.id) {
                                (link.closest('li') || link).hidden = ! match;
                            }
                        });
                    });

                    none.hidden = shown > 0;
                };

                filter.addEventListener('input', apply);
                if (important) {
                    important.addEventListener('click', function () {
                        important.setAttribute('aria-pressed', important.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                        apply();
                    });
                }
                root.querySelector('[data-rules-reset]').addEventListener('click', function () {
                    filter.value = '';
                    if (important) { important.setAttribute('aria-pressed', 'false'); }
                    apply();
                });

                // Copy the address of one rule.
                root.querySelectorAll('[data-copy-link]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var url = window.location.href.split('#')[0] + '#' + button.dataset.copyLink;
                        var done = function () {
                            var original = button.getAttribute('title');
                            button.setAttribute('title', button.dataset.copied);
                            button.classList.add('text-brand');
                            window.setTimeout(function () {
                                button.setAttribute('title', original);
                                button.classList.remove('text-brand');
                            }, 1500);
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(done);
                        } else {
                            window.location.hash = button.dataset.copyLink;
                            done();
                        }
                    });
                });

                // The index shows where the reader is.
                if ('IntersectionObserver' in window) {
                    var visible = {};
                    var mark = function () {
                        var first = items.find(function (item) { return visible[item.id]; });
                        links.forEach(function (link) {
                            var active = first && link.dataset.ruleLink === first.id;
                            if (active) { link.setAttribute('aria-current', 'true'); } else { link.removeAttribute('aria-current'); }
                        });
                        var strip = document.querySelector('[data-rules-strip] [aria-current="true"]');
                        if (strip && strip.offsetParent) {
                            var holder = strip.parentElement;
                            holder.scrollTo({ left: strip.offsetLeft - holder.clientWidth / 2 + strip.clientWidth / 2, behavior: 'smooth' });
                        }
                    };
                    var observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) { visible[entry.target.id] = entry.isIntersecting; });
                        mark();
                    }, { rootMargin: '-130px 0px -55% 0px' });
                    items.forEach(function (item) { observer.observe(item); });
                }
            })();
        </script>
    @endif
@endsection
