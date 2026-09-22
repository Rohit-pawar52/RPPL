@extends('layouts.admin')

@section('title', 'Registration Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.player-registrations.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to registrations
        </a>
        <a
            href="{{ route('admin.player-registrations.edit', $registration) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit / Verify Payment
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="font-mono text-xs text-neutral-500">{{ $registration->registration_number }}</p>
                <h2 class="text-base font-semibold text-neutral-900">{{ $registration->player->name }}</h2>
            </div>
            <x-status-badge :status="$registration->payment_status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            Registered for <span class="font-medium text-neutral-700">{{ $registration->edition->name }}</span>
            @unless($registration->player->is_active)
                &middot; <span class="text-neutral-400">player is currently inactive</span>
            @endunless
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Phone</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $registration->player->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Email</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $registration->player->email ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Date of birth</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ $registration->player->date_of_birth?->format('d M Y') ?? 'Not provided' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Age</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ $registration->player->date_of_birth?->age ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Player type</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ \App\Models\Player::PRIMARY_ROLE_LABELS[$registration->player->primary_role] ?? 'Not provided' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Registration fee</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Payment reference</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $registration->payment_reference ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">
                    OCR suggestion
                    <span class="text-neutral-300" title="Extracted automatically from the payment-proof upload — advisory only, never authoritative. Compare against Payment reference above before relying on it.">(?)</span>
                </dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    @if($registration->ocr_status === 'extracted' && $registration->ocr_transaction_id)
                        {{ $registration->ocr_transaction_id }}
                        @if($registration->hasDuplicateOcrTransactionId())
                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                also seen on another registration
                            </span>
                        @endif
                    @elseif($registration->ocr_status === 'pending')
                        <span class="text-neutral-400">Pending&hellip;</span>
                    @elseif($registration->ocr_status === 'not_found')
                        <span class="text-neutral-400">No reference found in proof</span>
                    @elseif($registration->ocr_status === 'failed')
                        <span class="text-neutral-400">Extraction failed</span>
                    @else
                        <span class="text-neutral-400">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Registered at</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ $registration->registered_at?->format('d M Y, h:i A') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Squad assignment</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    @if($registration->teamPlayer)
                        {{ $registration->teamPlayer->editionTeam->team->name }} (#{{ $registration->teamPlayer->jersey_number ?? '—' }})
                    @else
                        Not yet assigned
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Submitted documents</h3>

        <dl class="mt-3 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2">
                <span class="font-medium text-neutral-700">Aadhaar Document</span>
                @if($registration->aadhaar_document_path)
                    <a
                        href="{{ route('admin.player-registrations.aadhaar', $registration) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 font-medium theme-link theme-hover-primary-soft-bg"
                    >
                        View / Download
                    </a>
                @else
                    <span class="text-neutral-400">Not provided</span>
                @endif
            </div>
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2">
                <span class="font-medium text-neutral-700">Payment Proof</span>
                @if($registration->payment_proof_path)
                    <a
                        href="{{ route('admin.player-registrations.payment-proof', $registration) }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 font-medium theme-link theme-hover-primary-soft-bg"
                    >
                        View
                    </a>
                @else
                    <span class="text-neutral-400">Not provided</span>
                @endif
            </div>
        </dl>
    </div>
@endsection
