@extends('layouts.admin')

@section('title', 'Committee Member Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.committee-members.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to committee members
        </a>
        <a
            href="{{ route('admin.committee-members.edit', $member) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">{{ $member->name }}</h2>
            <x-status-badge :status="$member->is_active ? 'active' : 'inactive'" />
        </div>
        {{-- Admin-only page: phone may be shown here. --}}
        <p class="mt-1 text-xs text-neutral-500">{{ $member->phone ?: 'No phone on file' }}</p>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-stat-card label="Contributions" :value="$member->contributions_count" icon="clipboard" />
        <x-stat-card label="Total Contributed" :value="money($totalContributed)" icon="currency" />
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Contributions</h3>

        @forelse($member->contributions as $contribution)
            <div class="flex items-center justify-between border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                <span class="font-medium text-neutral-800">{{ $contribution->edition->name }}</span>
                <span class="flex items-center gap-3 text-neutral-500">
                    {{ $contribution->contributed_at->format('d M Y') }}
                    <span class="font-medium text-neutral-800">{{ money($contribution->amount) }}</span>
                </span>
            </div>
        @empty
            <p class="text-xs text-neutral-400">No contributions recorded yet.</p>
        @endforelse
    </div>
@endsection
