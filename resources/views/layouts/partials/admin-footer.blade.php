<footer class="adm-footer no-print">
    <div class="flex flex-col items-center justify-between gap-1.5 px-4 py-3.5 text-[11px] text-slate-400 sm:flex-row sm:px-6 lg:px-8">
        <p>&copy; {{ display_datetime(now(), 'Y') }} {{ $branding->applicationName }}</p>
        <p class="flex items-center gap-3">
            <a href="{{ route('public.home') }}" target="_blank" rel="noopener" class="font-medium text-slate-500 hover:text-brand">{{ __('View public site') }}</a>
            <span class="text-slate-300" aria-hidden="true">&middot;</span>
            <span>{{ __('Admin console') }}</span>
        </p>
    </div>
</footer>
