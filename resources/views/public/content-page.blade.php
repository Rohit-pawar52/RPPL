@extends('layouts.public')

@section('title', $title.' · '.$branding->applicationName)

{{--
    The fixed content pages (Privacy Policy, Terms & Conditions, FAQs): a
    comfortable reading column and, when the page has three or more sections,
    an "On this page" list that jumps to them (chips on a phone, a sticky list
    from lg up). The list is built in the browser from the page's own headings,
    so the sanitised HTML in $content is output exactly as it is.
--}}
@section('content')
    <div class="mx-auto max-w-3xl lg:data-[has-toc=on]:max-w-5xl" data-content-wrap>
        <x-public.page-header :title="$title" :back="route('public.home')" :back-label="__('ux_public_shell.content.back_home')" />

        <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-5 lg:gap-10 lg:[[data-has-toc=on]_&]:grid-cols-[13.5rem_minmax(0,46rem)]">
            <nav class="min-w-0 lg:sticky lg:top-24" data-toc hidden aria-label="{{ __('ux_public_shell.content.toc') }}">
                <p class="pub-eyebrow mb-2 hidden lg:block">{{ __('ux_public_shell.content.toc') }}</p>
                <div class="ps-toc-list" data-toc-list></div>
            </nav>

            <div class="min-w-0">
                <article class="pub-card p-5 sm:p-8">
                    @if($content)
                        <div class="ps-prose max-w-[68ch]" data-prose>
                            {!! $content !!}
                        </div>
                    @else
                        <x-public.empty icon="document-chart">{{ __('directory.content.empty') }}</x-public.empty>
                    @endif
                </article>

                @if($branding->hasContactDetails())
                    <aside class="mt-5 flex flex-col gap-3 rounded-2xl bg-brand-soft p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold text-slate-900">{{ __('ux_public_shell.content.help_title') }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ __('ux_public_shell.content.help_text') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($branding->contactEmail)
                                <a href="mailto:{{ $branding->contactEmail }}" class="btn btn-secondary">
                                    <x-icon name="mail" class="h-4 w-4" /> {{ __('ux_public_shell.content.email') }}
                                </a>
                            @endif
                            @if($branding->whatsappLink())
                                <a href="{{ $branding->whatsappLink() }}" target="_blank" rel="noopener" class="btn btn-secondary">
                                    <x-icon name="chat" class="h-4 w-4" /> WhatsApp
                                </a>
                            @endif
                            @if($branding->contactPhone)
                                <a href="tel:{{ $branding->contactPhone }}" class="btn btn-secondary">
                                    <x-icon name="phone" class="h-4 w-4" /> {{ __('ux_public_shell.content.call') }}
                                </a>
                            @endif
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            var prose = document.querySelector('[data-prose]');
            var toc = document.querySelector('[data-toc]');
            if (!prose || !toc) { return; }

            var headings = Array.prototype.slice.call(prose.querySelectorAll('h2'));
            if (headings.length < 3) { return; }

            var list = toc.querySelector('[data-toc-list]');
            var links = [];

            headings.forEach(function (heading, index) {
                if (!heading.id) { heading.id = 'section-' + (index + 1); }
                var link = document.createElement('a');
                link.href = '#' + heading.id;
                link.className = 'ps-toc-link';
                link.textContent = heading.textContent.trim();
                list.appendChild(link);
                links.push({ heading: heading, link: link });
            });
            toc.hidden = false;
            var wrap = document.querySelector('[data-content-wrap]');
            if (wrap) { wrap.dataset.hasToc = 'on'; }

            // Highlight the section being read.
            if (!('IntersectionObserver' in window)) { return; }
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) { return; }
                    links.forEach(function (item) {
                        item.link.classList.toggle('ps-toc-link-active', item.heading === entry.target);
                    });
                });
            }, { rootMargin: '-90px 0px -65% 0px' });
            headings.forEach(function (heading) { observer.observe(heading); });
        })();
    </script>
@endsection
