@extends('layouts.admin')

@section('title', 'Import Registrations')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.player-registrations.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to registrations
        </a>
    </div>

    @if($errors->has('csv_file'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <p class="mb-1 font-medium">Import failed &mdash; no changes were made:</p>
            <ul class="list-disc space-y-0.5 pl-4">
                @foreach($errors->get('csv_file') as $messages)
                    @foreach((array) $messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-xl rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.player-registrations.import.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <x-form.select
                name="edition_id"
                label="Edition"
                placeholder="Select edition"
                :options="$editions->pluck('name', 'id')"
                :value="old('edition_id')"
            />

            <x-form.input name="csv_file" label="CSV File" type="file" accept=".csv,text/csv,text/plain" required />

            <div class="mt-2 rounded-md border border-neutral-100 bg-neutral-50 p-3 text-xs text-neutral-600">
                <p class="mb-1 font-medium text-neutral-700">Expected CSV columns</p>
                <p class="mb-2">
                    Only <code class="rounded bg-neutral-200 px-1">name</code> is required. Column names are matched
                    case-insensitively with spaces treated as underscores, so a Google Forms export's headers work
                    as-is. Any other column (e.g. a form's "Timestamp") is ignored.
                </p>
                <code class="block overflow-x-auto rounded bg-neutral-200 px-2 py-1">
                    name,phone,email,registration_fee,payment_status,registered_at
                </code>
                <p class="mt-2">
                    <code class="rounded bg-neutral-200 px-1">payment_status</code> must be one of:
                    {{ implode(', ', \App\Models\PlayerRegistration::PAYMENT_STATUSES) }} (defaults to "pending" if left blank).
                    <code class="rounded bg-neutral-200 px-1">registered_at</code> accepts a plain date such as
                    2026-01-15. Existing players are matched by email, then phone &mdash; never by name alone. A
                    player already registered for the selected edition is skipped, not updated.
                </p>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Import
                </button>
                <a href="{{ route('admin.player-registrations.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
