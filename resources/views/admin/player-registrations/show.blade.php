@extends('layouts.admin')

@section('title', 'Registration Details')

@section('actions')
    <a href="{{ route('admin.player-registrations.edit', $registration) }}" class="btn btn-secondary">
        <x-icon name="pencil" class="h-4 w-4" />
        Edit / Verify Payment
    </a>
@endsection

@section('content')
    @php
        $player = $registration->player;
        $status = $registration->payment_status;
        $photoUrl = $registration->photo_path ? route('admin.player-registrations.photo', $registration) : null;
        $profileUrl = $player->photo_path ? media_url($player->photo_path, 'user') : null;
        $proofUrl = $registration->payment_proof_path ? route('admin.player-registrations.payment-proof', $registration) : null;
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $aadhaarImageUrl = $registration->aadhaar_document_path
            && in_array(strtolower(pathinfo($registration->aadhaar_document_path, PATHINFO_EXTENSION)), $imageExtensions, true)
            ? route('admin.player-registrations.aadhaar', $registration) : null;
        $utrMatchesScreenshot = $registration->ocrMatchesSubmittedUtr();
        $failReasons = ['UTR not found in the bank statement', 'Payment screenshot is unclear', 'Amount does not match the fee', 'This UTR was already used'];
        $canVerify = auth()->user()->can('update', $registration);
        $bowlingLabels = ['right_arm' => 'Right arm', 'left_arm' => 'Left arm', 'none' => "Doesn't bowl"];
        $ageFromDob = $player->date_of_birth?->age;
    @endphp

    <div class="mb-4">
        <a href="{{ route('admin.player-registrations.index', array_filter(['edition_id' => $registration->edition_id, 'payment_status' => $status === 'pending' ? 'pending' : null])) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to registrations
        </a>
    </div>

    {{-- Who it is, and the status at a glance. --}}
    <header class="ops-card ops-card-body mb-4 flex flex-wrap items-center gap-4">
        @if($photoUrl || $profileUrl)
            <a href="{{ $photoUrl ?? $profileUrl }}" data-lightbox data-caption="{{ $player->name }}" class="ops-thumb h-16 w-16 !rounded-2xl" aria-label="Enlarge the photo of {{ $player->name }}">
                <x-media-image :url="$photoUrl ?? $profileUrl" kind="user" alt="" />
            </a>
        @else
            <span class="ops-initials h-16 w-16 !rounded-2xl text-xl" aria-hidden="true">{{ \Illuminate\Support\Str::of($player->name)->substr(0, 1) }}</span>
        @endif
        <div class="min-w-0 flex-1 basis-40">
            <p class="font-mono text-xs text-slate-500">{{ $registration->registration_number }}</p>
            <h2 class="break-words text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $player->name }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                Registered for <span class="font-medium text-slate-700">{{ $registration->edition->name }}</span>
                @unless($player->is_active)
                    &middot; <span class="text-slate-400">player is currently inactive</span>
                @endunless
            </p>
        </div>
        <div class="flex w-full items-center justify-between gap-3 sm:w-auto">
            @if($player->phone)
                <a href="tel:{{ $player->phone }}" class="btn btn-secondary btn-sm" aria-label="Call {{ $player->name }}"><x-ops.icon name="phone" /> {{ $player->phone }}</a>
            @endif
            <x-status-badge :status="$status" />
        </div>
    </header>

    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-start">
        <div class="contents lg:block lg:space-y-4">
            {{-- The proof, big, with the numbers to compare it with. --}}
            <section class="ops-card order-1">
                <div class="ops-card-head">
                    <h3 class="ops-title">Payment proof</h3>
                    <span class="text-xs text-slate-500">Fee to pay:
                        <strong class="tabular-nums text-slate-800">{{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}</strong>
                    </span>
                </div>
                <div class="ops-card-body space-y-4">
                    @if($proofUrl)
                        <a href="{{ $proofUrl }}" data-lightbox data-caption="Payment screenshot &middot; {{ $player->name }}" class="block overflow-hidden rounded-xl border border-line bg-slate-50 transition hover:border-brand" aria-label="Enlarge the payment screenshot">
                            <x-media-image :url="$proofUrl" kind="image" alt="Payment screenshot" loading="lazy" class="mx-auto max-h-[28rem] w-full object-contain" />
                        </a>
                        <p class="-mt-2 text-center text-[11px] text-slate-400">Tap the picture to read it full screen.</p>
                    @else
                        <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                            <x-ops.icon name="image" class="h-7 w-7 text-slate-300" />
                            <p class="text-[13px] text-slate-500">No payment screenshot was uploaded.</p>
                        </div>
                    @endif

                    <dl class="grid gap-3 text-xs sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="ops-kicker">
                                UTR typed by the player
                                <span class="text-slate-300" title="What the player typed as their payment/transaction ID on the registration form. Unverified — compare it with the payment proof, then record the confirmed value as the Payment reference.">(?)</span>
                            </dt>
                            <dd class="mt-1 break-all font-mono text-sm font-semibold text-slate-900">
                                {{ $registration->submitted_utr ?? 'Not provided' }}
                            </dd>
                            <dd class="mt-1.5 flex flex-wrap gap-1">
                                @if($registration->hasDuplicateSubmittedUtr())
                                    <span class="ops-pill ops-pill-amber">also typed on another registration</span>
                                @endif
                                @if($utrMatchesScreenshot === true)
                                    <span class="ops-pill ops-pill-green">matches the screenshot</span>
                                @elseif($utrMatchesScreenshot === false)
                                    <span class="ops-pill ops-pill-amber">differs from the screenshot</span>
                                @endif
                            </dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="ops-kicker">
                                OCR suggestion
                                <span class="text-slate-300" title="Extracted automatically from the payment-proof upload — advisory only, never authoritative. Compare against Payment reference above before relying on it.">(?)</span>
                            </dt>
                            <dd class="mt-1 break-all font-mono text-sm font-semibold text-slate-900">
                                @if($registration->ocr_status === 'extracted' && $registration->ocr_transaction_id)
                                    {{ $registration->ocr_transaction_id }}
                                    @if($registration->hasDuplicateOcrTransactionId())
                                        <span class="ops-pill ops-pill-amber ml-1 font-sans">also seen on another registration</span>
                                    @endif
                                @elseif($registration->ocr_status === 'pending')
                                    <span class="font-sans font-normal text-slate-400">Pending&hellip;</span>
                                @elseif($registration->ocr_status === 'not_found')
                                    <span class="font-sans font-normal text-slate-400">No reference found in proof</span>
                                @elseif($registration->ocr_status === 'failed')
                                    <span class="font-sans font-normal text-slate-400">Extraction failed</span>
                                @else
                                    <span class="font-sans font-normal text-slate-400">—</span>
                                @endif
                            </dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 sm:col-span-2">
                            <dt class="ops-kicker">Payment reference (confirmed)</dt>
                            <dd class="mt-1 break-all font-mono text-sm font-semibold text-slate-900">{{ $registration->payment_reference ?? 'Not provided' }}</dd>
                        </div>
                        @if($status === 'failed' && $registration->payment_failure_reason)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 sm:col-span-2">
                                <dt class="ops-kicker !text-red-500">Failure reason (shown to the player)</dt>
                                <dd class="mt-1 text-[13px] font-medium text-red-700">{{ $registration->payment_failure_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            {{-- Documents --}}
            <section class="ops-card order-4">
                <div class="ops-card-head"><h3 class="ops-title">Submitted documents</h3></div>
                <div class="ops-card-body space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                        <span class="font-medium text-slate-700">Aadhaar Document</span>
                        @if($registration->aadhaar_document_path)
                            <a href="{{ route('admin.player-registrations.aadhaar', $registration) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">View / Download</a>
                        @else
                            <span class="text-slate-400">Not provided</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                        <span class="font-medium text-slate-700">Payment Proof</span>
                        <span class="flex flex-wrap items-center justify-end gap-2">
                            @if($registration->payment_proof_path)
                                <a href="{{ route('admin.player-registrations.payment-proof', $registration) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">View</a>
                            @endif
                            @if($registration->payment_proof_url)
                                <a href="{{ $registration->payment_proof_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Open in Google Drive</a>
                            @endif
                            @if(! $registration->payment_proof_path && ! $registration->payment_proof_url)
                                <span class="text-slate-400">Not provided</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                        <span class="font-medium text-slate-700">Photo</span>
                        <span class="flex flex-wrap items-center justify-end gap-2">
                            @if($registration->photo_path)
                                <a href="{{ route('admin.player-registrations.photo', $registration) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">View submitted photo</a>
                            @endif
                            @if($player->photo_path)
                                <a href="{{ media_url($player->photo_path, 'user') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">View profile photo</a>
                            @endif
                            @if($registration->photo_url)
                                <a href="{{ $registration->photo_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Open in Google Drive</a>
                            @endif
                            @if(! $registration->photo_path && ! $player->photo_path && ! $registration->photo_url)
                                <span class="text-slate-400">Not provided</span>
                            @endif
                        </span>
                    </div>

                    {{-- Pictures, so the files can be checked without opening each one.
                         A file that only has a Google Drive link cannot be previewed
                         (Drive needs a login), and a PDF Aadhaar opens with its button. --}}
                    @if($photoUrl || $profileUrl || $aadhaarImageUrl)
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            @if($photoUrl)
                                <figure>
                                    <figcaption class="ops-kicker mb-1">Submitted photo</figcaption>
                                    <a href="{{ $photoUrl }}" data-lightbox data-caption="Submitted photo" class="ops-thumb aspect-square w-full" aria-label="Enlarge the submitted photo">
                                        <x-media-image :url="$photoUrl" kind="user" alt="Submitted photo" loading="lazy" />
                                    </a>
                                </figure>
                            @endif
                            @if($profileUrl)
                                <figure>
                                    <figcaption class="ops-kicker mb-1">Profile photo (public)</figcaption>
                                    <a href="{{ $profileUrl }}" data-lightbox data-caption="Profile photo (public)" class="ops-thumb aspect-square w-full" aria-label="Enlarge the profile photo">
                                        <x-media-image :url="$profileUrl" kind="user" alt="Profile photo (public)" loading="lazy" />
                                    </a>
                                </figure>
                            @endif
                            @if($aadhaarImageUrl)
                                <figure>
                                    <figcaption class="ops-kicker mb-1">Aadhaar document</figcaption>
                                    <a href="{{ $aadhaarImageUrl }}" data-lightbox data-caption="Aadhaar document" class="ops-thumb aspect-square w-full" aria-label="Enlarge the Aadhaar document">
                                        <x-media-image :url="$aadhaarImageUrl" kind="image" alt="Aadhaar document" loading="lazy" />
                                    </a>
                                </figure>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        @if(($registration->photo_url && ! $registration->photo_path) || ($registration->payment_proof_url && ! $registration->payment_proof_path))
                            <form method="POST" action="{{ route('admin.player-registrations.fetch-files', $registration) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary">Copy files from Google Drive</button>
                            </form>
                        @endif
                        @if($registration->photo_path)
                            <form
                                method="POST"
                                action="{{ route('admin.player-registrations.profile-photo', $registration) }}"
                                @if($player->photo_path) onsubmit="return confirm('This replaces the current profile photo of this player. Continue?')" @endif
                            >
                                @csrf
                                <button type="submit" class="btn btn-secondary">Use as profile photo</button>
                            </form>
                        @endif
                    </div>

                    @if($registration->photo_url || $registration->payment_proof_url)
                        <p class="text-[11px] leading-4 text-slate-400">
                            A Google Drive link comes from an imported Google Form sheet. "Copy files from Google Drive" saves the
                            files here (they must be shared as "Anyone with the link"); otherwise open the link while signed in to the
                            Google account that owns the form. "Use as profile photo" resizes the submitted photo and shows it on the
                            public site.
                        </p>
                    @endif
                </div>
            </section>
        </div>

        {{-- The decision. On a phone it stays at the bottom edge of the screen while the
             proof above is read; on a wide screen it sits beside the proof. --}}
        <div class="contents lg:block lg:space-y-4">
            @if($canVerify)
            <section class="order-5 max-lg:sticky max-lg:bottom-0 max-lg:z-20 max-lg:-mx-4 max-lg:border-t max-lg:border-line max-lg:bg-white/95 max-lg:px-4 max-lg:py-3 max-lg:shadow-pop max-lg:backdrop-blur lg:rounded-xl lg:border lg:border-line lg:bg-white lg:p-5 lg:shadow-card" aria-label="Decide on this payment">
                <p class="ops-kicker mb-2 max-lg:hidden">Your decision</p>
                <div class="grid grid-cols-[1fr_1fr_auto] gap-2 lg:grid-cols-2 lg:gap-3">
                    <form method="POST" action="{{ route('admin.player-registrations.mark-paid', $registration) }}" class="contents">
                        @csrf
                        <button type="submit" class="btn btn-primary min-h-12 text-sm lg:min-h-14 lg:text-base">
                            <x-ops.icon name="check" class="h-5 w-5" /> Mark paid
                        </button>
                    </form>
                    <button
                        type="button"
                        class="btn btn-danger-soft min-h-12 text-sm lg:min-h-14 lg:text-base"
                        onclick="document.getElementById('mark-failed-panel').classList.toggle('hidden'); document.getElementById('failure-reason-input').focus()"
                    >
                        <x-ops.icon name="x" class="h-5 w-5" /> Mark failed
                    </button>
                    <a href="{{ route('admin.player-registrations.next-pending', $registration) }}" class="btn btn-secondary min-h-12 px-3 lg:col-span-2 lg:min-h-10" aria-label="Skip to the next pending registration">
                        <span class="max-lg:sr-only">Skip &mdash; next pending</span> <x-ops.icon name="arrow-right" class="h-5 w-5 lg:h-4 lg:w-4" />
                    </a>
                </div>

                <form
                    id="mark-failed-panel"
                    method="POST"
                    action="{{ route('admin.player-registrations.mark-failed', $registration) }}"
                    class="{{ $errors->has('reason') ? '' : 'hidden' }} mt-3 space-y-2"
                >
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        @foreach($failReasons as $reason)
                            <button type="button" class="ops-chip min-h-10 border-red-200 text-red-700 hover:bg-red-50" onclick="var i = document.getElementById('failure-reason-input'); i.value = this.textContent.trim(); this.form.requestSubmit();">{{ $reason }}</button>
                        @endforeach
                    </div>
                    <input
                        id="failure-reason-input"
                        type="text"
                        name="reason"
                        value="{{ old('reason') }}"
                        maxlength="255"
                        required
                        placeholder="Or type the reason, e.g. UTR not found in the bank statement"
                        aria-label="Reason the payment failed"
                        class="ops-input {{ $errors->has('reason') ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : '' }}"
                    />
                    @error('reason')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] text-slate-400">The player sees this reason when they check their registration status.</p>
                        <button type="submit" class="btn btn-danger shrink-0">Save as failed</button>
                    </div>
                </form>
            </section>
            @endif

            {{-- About the player --}}
            <section class="ops-card order-3">
                <div class="ops-card-head"><h3 class="ops-title">About the player</h3></div>
                <dl class="ops-card-body grid grid-cols-2 gap-x-4 gap-y-4 text-xs sm:grid-cols-3">
                    <div>
                        <dt class="ops-kicker">Phone</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $player->phone ?? '—' }}</dd>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <dt class="ops-kicker">Email</dt>
                        <dd class="mt-0.5 break-all text-[13px] font-medium text-slate-800">{{ $player->email ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Date of birth</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $player->date_of_birth?->format('d M Y') ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Age</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                            {{ $ageFromDob ?? $registration->age ?? '—' }}
                            @if($ageFromDob === null && $registration->age !== null)
                                <span class="font-normal text-slate-400">(as entered on the form)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Player type</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                            {{ \App\Models\Player::PRIMARY_ROLE_LABELS[$player->primary_role] ?? 'Not provided' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Batting hand</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                            {{ $player->batting_style ? ucfirst(str_replace('_', ' ', $player->batting_style)) : 'Not provided' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Bowling arm</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $bowlingLabels[$player->bowling_style] ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Village (Gram)</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $registration->village ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Tehsil</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $registration->tehsil ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">District</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $registration->district ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Registered at</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ display_datetime($registration->registered_at, 'd M Y, h:i A') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="ops-kicker">Registration fee</dt>
                        <dd class="mt-0.5 text-[13px] font-medium tabular-nums text-slate-800">{{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}</dd>
                    </div>
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="ops-kicker">Squad assignment</dt>
                        <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                            @if($registration->teamPlayer)
                                {{ $registration->teamPlayer->editionTeam->team->name }} (#{{ $registration->teamPlayer->jersey_number ?? '—' }})
                            @else
                                Not yet assigned
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>

    @include('admin.player-registrations._lightbox')
@endsection
