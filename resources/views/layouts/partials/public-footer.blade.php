<footer class="mt-6 bg-navy-950 text-slate-300">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-6 text-xs lg:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white">{{ $branding->applicationName }}</p>
                @if($branding->tagline)
                    <p class="mt-0.5 text-slate-400">{{ $branding->tagline }}</p>
                @endif
            </div>

            @if($branding->hasContactDetails())
                <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-400 sm:justify-end">
                    @if($branding->contactEmail)
                        <a href="mailto:{{ $branding->contactEmail }}" class="hover:text-white">{{ $branding->contactEmail }}</a>
                    @endif
                    @if($branding->contactPhone)
                        <a href="tel:{{ $branding->contactPhone }}" class="hover:text-white">{{ $branding->contactPhone }}</a>
                    @endif
                    @if($branding->contactWhatsapp)
                        @if($branding->whatsappLink())
                            <a href="{{ $branding->whatsappLink() }}" class="hover:text-white" target="_blank" rel="noopener">WhatsApp</a>
                        @else
                            <span>{{ $branding->contactWhatsapp }}</span>
                        @endif
                    @endif
                    @if($branding->contactAddress)
                        <span>{{ $branding->contactAddress }}</span>
                    @endif
                </p>
            @endif
        </div>

        <div class="flex flex-col gap-2 border-t border-white/10 pt-3 text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            @if($branding->footerText)
                <p>{{ $branding->footerText }}</p>
            @else
                <p>&copy; {{ display_datetime(now(), 'Y') }} {{ $branding->applicationName }}</p>
            @endif

            @if($footerContentPages->isNotEmpty())
                <p class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    @foreach($footerContentPages as $page)
                        <a href="{{ $page->publicUrl() }}" class="hover:text-white">{{ $page->title }}</a>
                    @endforeach
                </p>
            @endif
        </div>
    </div>
</footer>
