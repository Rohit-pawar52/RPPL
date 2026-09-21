<footer class="border-t border-neutral-200 bg-white">
    <div class="mx-auto flex w-full max-w-5xl flex-col items-center justify-between gap-1 px-4 py-3 text-[11px] text-neutral-400 sm:flex-row lg:px-6">
        @if($branding->footerText)
            <p>{{ $branding->footerText }}</p>
        @else
            <p>&copy; {{ now()->year }} {{ $branding->applicationName }}</p>
        @endif

        @if($branding->hasContactDetails())
            <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-0.5">
                @if($branding->contactEmail)
                    <a href="mailto:{{ $branding->contactEmail }}" class="hover:text-neutral-600">{{ $branding->contactEmail }}</a>
                @endif
                @if($branding->contactPhone)
                    <a href="tel:{{ $branding->contactPhone }}" class="hover:text-neutral-600">{{ $branding->contactPhone }}</a>
                @endif
                @if($branding->contactWhatsapp)
                    @if($branding->whatsappLink())
                        <a href="{{ $branding->whatsappLink() }}" class="hover:text-neutral-600" target="_blank" rel="noopener">WhatsApp</a>
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
</footer>
