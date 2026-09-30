<!DOCTYPE html>
<html lang="en" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') &middot; {{ $branding->shortName }} Admin</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')

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
    @endphp
    <script>
        window.flash = @json($flash);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-[13px] text-slate-800 antialiased">
    <div class="flex min-h-full">
        @include('layouts.partials.admin-sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.admin-header')

            <main class="min-w-0 flex-1 p-4 lg:p-6">
                {{-- Page header: title, optional one-line subtitle and action
                     buttons. Pages set them with @section('subtitle', '…')
                     and @section('actions') … @endsection. --}}
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="break-words text-xl font-semibold tracking-tight text-slate-900">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="mt-0.5 text-[13px] text-slate-500">@yield('subtitle')</p>
                        @endif
                    </div>

                    @hasSection('actions')
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @yield('actions')
                        </div>
                    @endif
                </div>

                @yield('content')
            </main>

            @include('layouts.partials.admin-footer')
        </div>
    </div>
</body>
</html>
