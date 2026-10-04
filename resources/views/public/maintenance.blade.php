<!DOCTYPE html>
<html lang="en" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding->applicationName }} &middot; Under Maintenance</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-surface text-[13px] text-slate-800 antialiased">
    <div class="flex min-h-full items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm text-center">
            <div class="mb-4 flex items-center justify-center">
                @if($branding->logoUrl)
                    <img
                        src="{{ $branding->logoUrl }}"
                        alt="{{ $branding->applicationName }}"
                        class="h-14 w-14 rounded-xl object-contain"
                    />
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-navy-900 text-xl font-bold text-white">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
            </div>

            <h1 class="pub-h1">{{ $branding->applicationName }}</h1>

            <div class="pub-card mt-4 p-6">
                <p class="text-sm leading-relaxed text-slate-700">
                    {{ $message ?: 'The website is currently under maintenance. Please check back shortly.' }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>
