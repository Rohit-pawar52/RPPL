<!DOCTYPE html>
<html lang="en" class="h-full bg-neutral-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding->applicationName }} &middot; Under Maintenance</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @vite(['resources/css/app.css'])
</head>
<body class="h-full text-[13px] text-neutral-800 antialiased">
    <div class="flex min-h-full items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm text-center">
            <div class="mb-4 flex items-center justify-center">
                @if($branding->logoUrl)
                    <img
                        src="{{ $branding->logoUrl }}"
                        alt="{{ $branding->applicationName }}"
                        class="h-12 w-12 rounded-md object-contain"
                    />
                @else
                    <span class="flex h-12 w-12 items-center justify-center rounded-md bg-blue-600 text-lg font-bold text-white">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
            </div>

            <h1 class="text-base font-semibold text-neutral-900">{{ $branding->applicationName }}</h1>

            <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-neutral-700">
                    {{ $message ?: 'The website is currently under maintenance. Please check back shortly.' }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>
