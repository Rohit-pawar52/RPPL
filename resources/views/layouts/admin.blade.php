<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') &middot; {{ $branding->shortName }} Admin</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')
    @include('layouts.partials.admin-js-strings')

    {{-- Applies the remembered desktop sidebar state before first paint so
         the sidebar never flashes open and then collapses. --}}
    <script>
        try {
            if (localStorage.getItem('rppl.admin.sidebar') === 'collapsed') {
                document.documentElement.dataset.sidebar = 'collapsed';
            }
        } catch (e) {}
    </script>

    @php
        $flash = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info'),
        ];
        // Resolved once for both the sidebar and the top bar.
        $navigation = app(\App\Support\AdminNavigation::class)->forUser(auth()->user());
    @endphp
    <script>
        window.flash = @json($flash);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-app h-full text-[13px] text-slate-800 antialiased">
    <a href="#admin-main" class="sr-only z-[100] rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-pop focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to content</a>

    <div class="flex min-h-full">
        @include('layouts.partials.admin-sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.admin-header')

            <main id="admin-main" class="min-w-0 flex-1 px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
                <div class="mx-auto w-full max-w-[1600px]">
                    {{-- Page header: title, optional one-line subtitle and action
                         buttons. Pages set them with @section('subtitle', '…')
                         and @section('actions') … @endsection. A page that draws
                         its own heading (the dashboard) sets @section('bare', '1'). --}}
                    @if(! $__env->hasSection('bare'))
                        <div class="adm-page-head">
                            <div class="min-w-0">
                                <h1 class="adm-page-title">@yield('title', 'Dashboard')</h1>
                                @hasSection('subtitle')
                                    <p class="adm-page-sub">@yield('subtitle')</p>
                                @endif
                            </div>

                            @hasSection('actions')
                                <div class="adm-page-actions no-print">
                                    @yield('actions')
                                </div>
                            @endif
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            @include('layouts.partials.admin-footer')
        </div>
    </div>
</body>
</html>
