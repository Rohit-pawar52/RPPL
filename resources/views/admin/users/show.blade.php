@extends('layouts.admin')

@section('title', 'User Details')

@section('actions')
    <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary" icon="arrow-left">Back to users</x-admin.button>
    <x-admin.button href="{{ route('admin.users.edit', $targetUser) }}" icon="pencil">Edit</x-admin.button>
@endsection

@section('content')
    <x-admin.card class="max-w-3xl">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xl font-bold uppercase text-white">{{ \Illuminate\Support\Str::substr($targetUser->name, 0, 1) }}</span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-lg font-bold tracking-tight text-slate-900">
                    {{ $targetUser->name }}
                    @if($targetUser->id === auth()->id())
                        <span class="text-xs font-medium text-slate-400">(you)</span>
                    @endif
                </h2>
                <p class="truncate text-[13px] text-slate-500">{{ $targetUser->email }}</p>
            </div>
            <x-status-badge :status="$targetUser->is_active ? 'active' : 'inactive'" />
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-5 sm:grid-cols-3">
            <div class="col-span-2 sm:col-span-1">
                <dt class="text-xs text-slate-500">Email</dt>
                <dd class="mt-0.5 break-all text-[13px] font-semibold text-slate-900">{{ $targetUser->email }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Role</dt>
                <dd class="mt-0.5 text-[13px] font-semibold text-slate-900">{{ $targetUser->role->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Created</dt>
                <dd class="mt-0.5 text-[13px] font-semibold text-slate-900">{{ display_datetime($targetUser->created_at, 'd M Y') }}</dd>
            </div>
        </dl>
    </x-admin.card>
@endsection
