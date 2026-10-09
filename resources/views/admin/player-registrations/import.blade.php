@extends('layouts.admin')

@section('title', __('Import Registrations'))

@section('subtitle', __('Bring in the responses of a Google Form sheet in three steps.'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.player-registrations.index') }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Back to registrations') }}
        </a>
    </div>

    @if($errors->has('csv_file'))
        <div class="mb-4 max-w-3xl rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700" role="alert">
            <p class="mb-1 font-semibold">{{ __('Import failed — no changes were made:') }}</p>
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
        <div class="mb-4 max-w-3xl rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs text-sky-900" role="status">
            <p class="font-semibold">{!! str_replace('—', '&mdash;', e(__('Check only — nothing was imported.'))) !!}</p>
            <p class="mt-1">
                {{ __('Importing this file would create :registrations (:players new) and skip :rows.', [
                    'registrations' => $check['created_registrations'] == 1 ? __(':count registration', ['count' => 1]) : __(':count registrations', ['count' => $check['created_registrations']]),
                    'players' => $check['created_players'] == 1 ? __(':count player', ['count' => 1]) : __(':count players', ['count' => $check['created_players']]),
                    'rows' => $check['skipped'] == 1 ? __(':count row', ['count' => 1]) : __(':count rows', ['count' => $check['skipped']]),
                ]) }}
            </p>
            <div class="mt-3 rounded-lg bg-white p-3 text-slate-700">
                @include('admin.player-registrations._import_notes', ['notes' => $check['notes']])
            </div>
            <p class="mt-3">
                {{ __('Looks right? Choose the file again, untick “Check only” and click Import.') }}
            </p>
        </div>
    @endif

    {{-- The form on the left; how importing works beside it on wide screens
         (below the form on a phone), so the form itself stays short. --}}
    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,32rem)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('admin.player-registrations.import.store') }}" enctype="multipart/form-data" novalidate class="space-y-4">
            @csrf

            <section class="ops-card">
                <div class="ops-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-brand-fg">1</span>
                        <h3 class="ops-title">{{ __('Choose the season') }}</h3>
                    </div>
                </div>
                <div class="ops-card-body">
                    <x-form.select
                        name="edition_id"
                        :label="__('Edition')"
                        :placeholder="__('Select edition')"
                        :options="$editions->pluck('name', 'id')"
                        :value="old('edition_id')"
                    />
                </div>
            </section>

            <section class="ops-card">
                <div class="ops-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-brand-fg">2</span>
                        <h3 class="ops-title">{{ __('Add the file') }}</h3>
                    </div>
                    <a href="{{ route('admin.player-registrations.import.sample') }}" class="btn btn-secondary btn-sm">
                        <x-ops.icon name="download" class="h-3.5 w-3.5" /> {{ __('Sample Excel sheet') }}
                    </a>
                </div>
                <div class="ops-card-body">
                    <x-form.file name="csv_file" :label="__('Excel or CSV file')" :prompt="__('Choose Excel or CSV file')" accept=".xlsx,.csv,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
                    <p class="-mt-2 text-[11px] text-slate-500">
                        {{ __('An Excel file (.xlsx, first sheet) or a CSV.') }}
                        {!! __(':link with the columns and two example players — replace them with your data and upload it.', ['link' => '<a href="'.e(route('admin.player-registrations.import.sample')).'" class="font-medium text-link hover:underline">'.e(__('Download a sample Excel sheet')).'</a>']) !!}
                    </p>
                </div>
            </section>

            <section class="ops-card">
                <div class="ops-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-brand-fg">3</span>
                        <h3 class="ops-title">{{ __('Check, then import') }}</h3>
                    </div>
                </div>
                <div class="ops-card-body">
                    <x-form.checkbox
                        name="dry_run"
                        :label="__('Check only — show what would be imported, but don\'t import anything')"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">{{ __('Import') }}</button>
                        <a href="{{ route('admin.player-registrations.index') }}" class="btn btn-secondary btn-lg">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </section>
        </form>

        <aside class="space-y-4">
            <div class="ops-card ops-card-body text-xs text-slate-600">
                <p class="mb-2 text-sm font-semibold tracking-tight text-slate-900">{{ __('Importing the Google Form sheet') }}</p>
                <p class="mb-2">
                    {!! __('Download the response sheet from Google Sheets (:xlsx or :csv) and upload it as it is — there is no need to rename or delete any column. Only :name is required; every other answer is optional.', [
                        'xlsx' => '<em>File → Download → Microsoft Excel (.xlsx)</em>',
                        'csv' => '<em>Comma-separated values (.csv)</em>',
                        'name' => '<strong>Name</strong>',
                    ]) !!}
                </p>
                <ul class="mb-2 list-disc space-y-1 pl-4">
                    <li>{!! __(':column becomes the registration date (read as day/month/year, in the display timezone).', ['column' => '<strong>Timestamp</strong>']) !!}</li>
                    <li>
                        {!! __(':column is how people are recognised: +91, spaces and dashes are cleaned up, and anyone already registered for the edition is skipped.', ['column' => '<strong>Mobile Number</strong>']) !!}
                    </li>
                    <li>
                        {!! __(':column is saved on a new player only if no other player has it — in a Google Form it is the account that filled the form, which is often shared.', ['column' => '<strong>Email Address</strong>']) !!}
                    </li>
                    <li>
                        {!! __(':age is saved as entered (the date of birth stays empty). :role and :hand (saved as the batting hand) and, if the sheet has it, :arm fill the player\'s profile.', [
                            'age' => '<strong>Age</strong>',
                            'role' => '<strong>Role</strong>',
                            'hand' => '<strong>Left hand/right hand</strong>',
                            'arm' => '<strong>Bowling arm</strong>',
                        ]) !!}
                    </li>
                    <li>
                        {!! __(':columns and the payment answer (“Scan and pay…”, saved as the UTR the player typed) are saved on the registration.', ['columns' => '<strong>Gram, Tehsil, District</strong>']) !!}
                    </li>
                    <li>
                        {!! __(':title — a CSV only holds their Google Drive links. The links are saved and shown on the registration page so you can open the originals; a player photo can later be uploaded from Players → Edit.', ['title' => '<strong>'.e(__('Photos and payment screenshots cannot be imported')).'</strong>']) !!}
                    </li>
                    <li>{!! __('Payment status starts as :status, and the fee is the edition\'s registration fee.', ['status' => '<em>'.e(__('pending')).'</em>']) !!}</li>
                </ul>
                <p>
                    {{ __("Importing the same sheet again is safe: players who are already registered are skipped and never changed, so only new responses are added. Anything that had to be cleaned up or left out is listed afterwards, and every value can be corrected later from the registration's Edit page.") }}
                </p>
            </div>

            <details class="ops-card ops-card-body text-xs text-slate-600">
                <summary class="cursor-pointer text-sm font-semibold tracking-tight text-slate-900">{{ __('Simple format (also accepted)') }}</summary>
                <code class="mt-3 block overflow-x-auto rounded-lg bg-slate-100 px-2 py-1.5">
                    name,phone,email,registration_fee,payment_status,registered_at
                </code>
                <p class="mt-2">
                    {!! __(':status must be one of: :list (defaults to "pending" if left blank).', ['status' => '<code class="rounded bg-slate-100 px-1">payment_status</code>', 'list' => e(implode(', ', \App\Models\PlayerRegistration::PAYMENT_STATUSES))]) !!}
                    {!! __(':date accepts a plain date such as 2026-01-15. A blank :fee becomes the edition\'s registration fee. In this format existing players are matched by email, then phone — never by name alone. A player already registered for the selected edition is skipped, not updated.', ['date' => '<code class="rounded bg-slate-100 px-1">registered_at</code>', 'fee' => '<code class="rounded bg-slate-100 px-1">registration_fee</code>']) !!}
                </p>
            </details>
        </aside>
    </div>
@endsection
