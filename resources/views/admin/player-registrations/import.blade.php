@extends('layouts.admin')

@section('title', 'Import Registrations')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.player-registrations.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to registrations
        </a>
    </div>

    @if($errors->has('csv_file'))
        <div class="mb-4 max-w-3xl rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
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

    @if(session('import_check'))
        @php $check = session('import_check'); @endphp
        <div class="mb-4 max-w-3xl rounded-md border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900" role="status">
            <p class="font-medium">Check only &mdash; nothing was imported.</p>
            <p class="mt-1">
                Importing this file would create
                {{ $check['created_registrations'] }} {{ \Illuminate\Support\Str::plural('registration', $check['created_registrations']) }}
                ({{ $check['created_players'] }} new {{ \Illuminate\Support\Str::plural('player', $check['created_players']) }})
                and skip {{ $check['skipped'] }} {{ \Illuminate\Support\Str::plural('row', $check['skipped']) }}.
            </p>
            <div class="mt-3 rounded-md bg-white p-3 text-slate-700">
                @include('admin.player-registrations._import_notes', ['notes' => $check['notes']])
            </div>
            <p class="mt-3">
                Looks right? Choose the file again, untick &ldquo;Check only&rdquo; and click Import.
            </p>
        </div>
    @endif

    {{-- The form on the left; how importing works beside it on wide screens
         (below the form on a phone), so the form itself stays short. --}}
    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.player-registrations.import.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <x-form.select
                name="edition_id"
                label="Edition"
                placeholder="Select edition"
                :options="$editions->pluck('name', 'id')"
                :value="old('edition_id')"
            />

            <x-form.file name="csv_file" label="Excel or CSV file" prompt="Choose Excel or CSV file" accept=".xlsx,.csv,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
            <p class="-mt-2 mb-3 text-[11px] text-slate-500">
                An Excel file (.xlsx, first sheet) or a CSV.
                <a href="{{ route('admin.player-registrations.import.sample') }}" class="font-medium text-green-700 hover:underline">Download a sample Excel sheet</a>
                with the columns and two example players &mdash; replace them with your data and upload it.
            </p>

            <x-form.checkbox
                name="dry_run"
                label="Check only — show what would be imported, but don't import anything"
            />

            <div class="mt-3 flex items-center gap-2">
                <x-admin.button>Import</x-admin.button>
                <x-admin.button href="{{ route('admin.player-registrations.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>

    <aside class="space-y-3 lg:max-w-2xl">
            <div class="rounded-md border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600">
                <p class="mb-1 font-medium text-slate-700">Importing the Google Form sheet</p>
                <p class="mb-2">
                    Download the response sheet from Google Sheets
                    (<em>File &rarr; Download &rarr; Microsoft Excel (.xlsx)</em> or <em>Comma-separated values (.csv)</em>)
                    and upload it as it is &mdash;
                    there is no need to rename or delete any column. Only <strong>Name</strong> is required; every
                    other answer is optional.
                </p>
                <ul class="mb-2 list-disc space-y-1 pl-4">
                    <li><strong>Timestamp</strong> becomes the registration date (read as day/month/year, in the display timezone).</li>
                    <li>
                        <strong>Mobile Number</strong> is how people are recognised: +91, spaces and dashes are cleaned up,
                        and anyone already registered for the edition is skipped.
                    </li>
                    <li>
                        <strong>Email Address</strong> is saved on a new player only if no other player has it &mdash; in a
                        Google Form it is the account that filled the form, which is often shared.
                    </li>
                    <li>
                        <strong>Age</strong> is saved as entered (the date of birth stays empty). <strong>Role</strong> and
                        <strong>Left hand/right hand</strong> (saved as the batting hand) and, if the sheet has it, <strong>Bowling arm</strong> fill the player's profile.
                    </li>
                    <li>
                        <strong>Gram, Tehsil, District</strong> and the payment answer
                        (&ldquo;Scan and pay&hellip;&rdquo;, saved as the UTR the player typed) are saved on the registration.
                    </li>
                    <li>
                        <strong>Photos and payment screenshots cannot be imported</strong> &mdash; a CSV only holds their
                        Google Drive links. The links are saved and shown on the registration page so you can open the
                        originals; a player photo can later be uploaded from Players &rarr; Edit.
                    </li>
                    <li>Payment status starts as <em>pending</em>, and the fee is the edition's registration fee.</li>
                </ul>
                <p>
                    Importing the same sheet again is safe: players who are already registered are skipped and never
                    changed, so only new responses are added. Anything that had to be cleaned up or left out is listed
                    afterwards, and every value can be corrected later from the registration's Edit page.
                </p>
            </div>

            <div class="rounded-md border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600">
                <p class="mb-1 font-medium text-slate-700">Simple format (also accepted)</p>
                <code class="block overflow-x-auto rounded bg-slate-200 px-2 py-1">
                    name,phone,email,registration_fee,payment_status,registered_at
                </code>
                <p class="mt-2">
                    <code class="rounded bg-slate-200 px-1">payment_status</code> must be one of:
                    {{ implode(', ', \App\Models\PlayerRegistration::PAYMENT_STATUSES) }} (defaults to "pending" if left blank).
                    <code class="rounded bg-slate-200 px-1">registered_at</code> accepts a plain date such as
                    2026-01-15. A blank <code class="rounded bg-slate-200 px-1">registration_fee</code> becomes the
                    edition's registration fee. In this format existing players are matched by email, then phone
                    &mdash; never by name alone. A player already registered for the selected edition is skipped, not
                    updated.
                </p>
            </div>
    </aside>
    </div>
@endsection
