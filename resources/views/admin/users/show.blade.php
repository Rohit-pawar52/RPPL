@extends('layouts.admin')

@section('title', 'User Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.users.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to users
        </a>
        <a
            href="{{ route('admin.users.edit', $targetUser) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $targetUser->name }}
                @if($targetUser->id === auth()->id())
                    <span class="text-xs font-normal text-neutral-400">(you)</span>
                @endif
            </h2>
            <x-status-badge :status="$targetUser->is_active ? 'active' : 'inactive'" />
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Email</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $targetUser->email }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Role</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $targetUser->role->name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Created</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $targetUser->created_at->format('d M Y') }}</dd>
            </div>
        </dl>
    </div>
@endsection
