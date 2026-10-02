@extends('layouts.admin')

@section('title', 'Registration Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.player-registrations.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to registrations
        </a>
        <a
            href="{{ route('admin.player-registrations.edit', $registration) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit / Verify Payment
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="font-mono text-xs text-slate-500">{{ $registration->registration_number }}</p>
                <h2 class="text-base font-semibold text-slate-900">{{ $registration->player->name }}</h2>
            </div>
            <x-status-badge :status="$registration->payment_status" />
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Registered for <span class="font-medium text-slate-700">{{ $registration->edition->name }}</span>
            @unless($registration->player->is_active)
                &middot; <span class="text-slate-400">player is currently inactive</span>
            @endunless
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-slate-400">Phone</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->player->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Email</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->player->email ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Date of birth</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $registration->player->date_of_birth?->format('d M Y') ?? 'Not provided' }}
                </dd>
            </div>
            @php $ageFromDob = $registration->player->date_of_birth?->age; @endphp
            <div>
                <dt class="text-slate-400">Age</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $ageFromDob ?? $registration->age ?? '—' }}
                    @if($ageFromDob === null && $registration->age !== null)
                        <span class="font-normal text-slate-400">(as entered on the form)</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Player type</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ \App\Models\Player::PRIMARY_ROLE_LABELS[$registration->player->primary_role] ?? 'Not provided' }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Batting hand</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $registration->player->batting_style ? ucfirst(str_replace('_', ' ', $registration->player->batting_style)) : 'Not provided' }}
                </dd>
            </div>
            @php
                $bowlingLabels = ['right_arm' => 'Right arm', 'left_arm' => 'Left arm', 'none' => "Doesn't bowl"];
            @endphp
            <div>
                <dt class="text-slate-400">Bowling arm</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $bowlingLabels[$registration->player->bowling_style] ?? 'Not provided' }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Village (Gram)</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->village ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Tehsil</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->tehsil ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">District</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->district ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Registration fee</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Payment reference</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ $registration->payment_reference ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">
                    UTR typed by the player
                    <span class="text-slate-300" title="What the player typed as their payment/transaction ID on the registration form. Unverified — compare it with the payment proof, then record the confirmed value as the Payment reference.">(?)</span>
                </dt>
                @php $utrMatchesScreenshot = $registration->ocrMatchesSubmittedUtr(); @endphp
                <dd class="mt-0.5 break-all font-medium text-slate-800">
                    {{ $registration->submitted_utr ?? 'Not provided' }}
                    @if($registration->hasDuplicateSubmittedUtr())
                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                            also typed on another registration
                        </span>
                    @endif
                    @if($utrMatchesScreenshot === true)
                        <span class="ml-1 inline-flex items-center rounded-full bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-inset ring-green-200">
                            matches the screenshot
                        </span>
                    @elseif($utrMatchesScreenshot === false)
                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                            differs from the screenshot
                        </span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">
                    OCR suggestion
                    <span class="text-slate-300" title="Extracted automatically from the payment-proof upload — advisory only, never authoritative. Compare against Payment reference above before relying on it.">(?)</span>
                </dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    @if($registration->ocr_status === 'extracted' && $registration->ocr_transaction_id)
                        {{ $registration->ocr_transaction_id }}
                        @if($registration->hasDuplicateOcrTransactionId())
                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                also seen on another registration
                            </span>
                        @endif
                    @elseif($registration->ocr_status === 'pending')
                        <span class="text-slate-400">Pending&hellip;</span>
                    @elseif($registration->ocr_status === 'not_found')
                        <span class="text-slate-400">No reference found in proof</span>
                    @elseif($registration->ocr_status === 'failed')
                        <span class="text-slate-400">Extraction failed</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Registered at</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $registration->registered_at?->format('d M Y, h:i A') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Squad assignment</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    @if($registration->teamPlayer)
                        {{ $registration->teamPlayer->editionTeam->team->name }} (#{{ $registration->teamPlayer->jersey_number ?? '—' }})
                    @else
                        Not yet assigned
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted documents</h3>

        <dl class="mt-3 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
            <div class="flex items-center justify-between gap-3 rounded-md border border-slate-100 px-3 py-2">
                <span class="font-medium text-slate-700">Aadhaar Document</span>
                @if($registration->aadhaar_document_path)
                    <a
                        href="{{ route('admin.player-registrations.aadhaar', $registration) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                    >
                        View / Download
                    </a>
                @else
                    <span class="text-slate-400">Not provided</span>
                @endif
            </div>
            <div class="flex items-center justify-between gap-3 rounded-md border border-slate-100 px-3 py-2">
                <span class="font-medium text-slate-700">Payment Proof</span>
                <span class="flex flex-wrap items-center justify-end gap-2">
                    @if($registration->payment_proof_path)
                        <a
                            href="{{ route('admin.player-registrations.payment-proof', $registration) }}"
                            target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                        >
                            View
                        </a>
                    @endif
                    @if($registration->payment_proof_url)
                        <a
                            href="{{ $registration->payment_proof_url }}"
                            target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                        >
                            Open in Google Drive
                        </a>
                    @endif
                    @if(! $registration->payment_proof_path && ! $registration->payment_proof_url)
                        <span class="text-slate-400">Not provided</span>
                    @endif
                </span>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-md border border-slate-100 px-3 py-2">
                <span class="font-medium text-slate-700">Photo</span>
                <span class="flex flex-wrap items-center justify-end gap-2">
                    @if($registration->photo_path)
                        <a
                            href="{{ route('admin.player-registrations.photo', $registration) }}"
                            target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                        >
                            View submitted photo
                        </a>
                    @endif
                    @if($registration->player->photo_path)
                        <a
                            href="{{ \Illuminate\Support\Facades\Storage::url($registration->player->photo_path) }}"
                            target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                        >
                            View profile photo
                        </a>
                    @endif
                    @if($registration->photo_url)
                        <a
                            href="{{ $registration->photo_url }}"
                            target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 font-medium text-green-700 hover:bg-green-50"
                        >
                            Open in Google Drive
                        </a>
                    @endif
                    @if(! $registration->photo_path && ! $registration->player->photo_path && ! $registration->photo_url)
                        <span class="text-slate-400">Not provided</span>
                    @endif
                </span>
            </div>
        </dl>

        @if($registration->photo_url || $registration->payment_proof_url)
            <p class="mt-3 text-[11px] text-slate-400">
                A Google Drive link comes from an imported Google Form sheet &mdash; the file itself stays on Drive, so
                open it while signed in to the Google account that owns the form. The player's own photo can be uploaded
                from Players &rarr; Edit.
            </p>
        @endif
    </div>
@endsection
