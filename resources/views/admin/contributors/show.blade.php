@extends('layouts.admin')

@section('title', 'Contributor Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.contributors.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to contributors
        </a>
        <a
            href="{{ route('admin.contributors.edit', $contributor) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-start gap-3">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($contributor->photo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($contributor->photo_path) }}"
                        alt="{{ $contributor->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="camera" class="h-6 w-6" />
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-neutral-900">{{ $contributor->name }}</h2>
                    <x-status-badge :status="$contributor->is_active ? 'active' : 'inactive'" />
                </div>
                {{-- Admin-only page: phone may be shown here. --}}
                <p class="mt-1 text-xs text-neutral-500">{{ $contributor->phone ?: 'No phone on file' }}</p>
            </div>
        </div>

        <p class="mt-3 text-xs text-neutral-500">
            <span class="font-medium text-neutral-600">Linked committee member:</span>
            @if($contributor->committeeMember)
                <a href="{{ route('admin.committee-members.show', $contributor->committeeMember) }}" class="text-blue-600 hover:underline">
                    {{ $contributor->committeeMember->name }}
                </a>
            @else
                Not linked
            @endif
        </p>

        <p class="mt-1 text-xs text-neutral-500">
            <span class="font-medium text-neutral-600">Contributions recorded:</span>
            {{ $contributor->contributions_count }}
        </p>
    </div>
@endsection
