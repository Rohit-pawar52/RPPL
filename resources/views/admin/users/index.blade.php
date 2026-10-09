@extends('layouts.admin')

@section('title', __('Users'))

@section('subtitle', __('Admin panel accounts and their roles.'))

@section('actions')
    <x-admin.button href="{{ route('admin.users.create') }}" variant="primary" icon="plus">{{ __('New user') }}</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <x-table-filters :action="route('admin.users.index')" :filters="$filters">
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="{{ __('Search name or email…') }}"
                aria-label="{{ __('Search users') }}"
                class="w-full sm:w-64"
            />

            <select name="role_id" aria-label="{{ __('Role') }}">
                <option value="">{{ __('All roles') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </x-table-filters>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>{{ __('User') }}</th>
                    <th class="hidden sm:table-cell">{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-bold uppercase text-white">{{ \Illuminate\Support\Str::substr($user->name, 0, 1) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.users.show', $user) }}" class="block truncate font-semibold text-slate-900 hover:text-link hover:underline">{{ $user->name }}@if($user->id === auth()->id()) <span class="ml-1 text-[10px] font-medium text-slate-400">{{ __('(you)') }}</span>@endif</a>
                                    <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                                    <span class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 sm:hidden">{{ $user->role->name }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell">{{ $user->role->name }}</td>
                        <td><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.users.show', $user) }}"
                                    title="{{ __('View') }}"
                                    aria-label="{{ __('View :name', ['name' => $user->name]) }}"
                                    class="btn btn-ghost btn-icon btn-sm max-sm:hidden"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.users.edit', $user) }}"
                                    title="{{ __('Edit') }}"
                                    aria-label="{{ __('Edit :name', ['name' => $user->name]) }}"
                                    class="btn btn-ghost btn-icon btn-sm max-sm:min-h-10 max-sm:w-10"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="4" icon="users">
                        {{ __('No users found.') }}
                        @if(array_filter($filters))
                            <x-slot:action>
                                <x-admin.button :href="route('admin.users.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                            </x-slot:action>
                        @endif
                    </x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
