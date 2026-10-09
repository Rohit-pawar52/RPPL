{{-- What a CSV import (or a "check only" run) found worth telling the admin.
     $notes = ['info' => [...], 'skipped' => [...], 'adjustments' => [...]] — plain
     sentences, escaped like any other text. --}}
@php
    $info = $notes['info'] ?? [];
    $skipped = $notes['skipped'] ?? [];
    $adjustments = $notes['adjustments'] ?? [];
@endphp

<div class="space-y-3 text-xs text-slate-700">
    @if($info)
        <ul class="list-disc space-y-0.5 pl-4 text-slate-600">
            @foreach($info as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif

    @if($skipped)
        <div>
            <p class="mb-1 font-medium text-slate-800">{{ __('Rows not imported') }}</p>
            <ul class="max-h-56 list-disc space-y-0.5 overflow-y-auto pl-4">
                @foreach($skipped as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($adjustments)
        <div>
            <p class="mb-1 font-medium text-slate-800">{{ __('Imported, but worth a look') }}</p>
            <p class="mb-1 text-slate-500">
                {{ __("These values were cleaned up, left empty, or matched to an existing player. Each one can be corrected from that registration's Edit page.") }}
            </p>
            <ul class="max-h-72 list-disc space-y-0.5 overflow-y-auto pl-4">
                @foreach($adjustments as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
