@extends('layouts.admin')

@section('title', __('Roles'))

@section('subtitle', __('What each kind of login is allowed to do. Administrators always have every permission.'))

@section('actions')
    @can('create', \App\Models\Role::class)
        <x-admin.button href="{{ route('admin.roles.create') }}" variant="primary" icon="plus">{{ __('New role') }}</x-admin.button>
    @endcan
@endsection

@section('content')
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th class="hidden md:table-cell">{{ __('Code') }}</th>
                    <th class="text-right">{{ __('Users') }}</th>
                    <th class="text-right">{{ __('Permissions') }}</th>
                    <th class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <a href="{{ route('admin.roles.show', $role) }}" class="font-semibold text-slate-900 hover:text-link hover:underline">{{ $role->name }}</a>
                                @if($role->isSystem())
                                    @include('admin.roles._built-in-badge')
                                @endif
                            </div>
                        </td>
                        <td class="hidden font-mono text-[12px] text-slate-500 md:table-cell">{{ $role->slug }}</td>
                        <td class="num">
                            @if($role->users_count > 0)
                                @can('viewAny', \App\Models\User::class)
                                    <a href="{{ route('admin.users.index', ['role_id' => $role->id]) }}" class="font-semibold text-link hover:text-link-hover hover:underline">{{ $role->users_count }}</a>
                                @else
                                    {{ $role->users_count }}
                                @endcan
                            @else
                                <span class="text-slate-400">0</span>
                            @endif
                        </td>
                        <td class="num">
                            {{ $role->isAdmin() ? __('All') : ($permissionCounts[$role->id] ?? 0) }}
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.roles.show', $role) }}"
                                    title="{{ __('View') }}"
                                    aria-label="{{ __('View :name', ['name' => $role->name]) }}"
                                    class="btn btn-ghost btn-icon btn-sm max-sm:hidden"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                @can('update', $role)
                                    <a
                                        href="{{ route('admin.roles.edit', $role) }}"
                                        title="{{ __('Edit') }}"
                                        aria-label="{{ __('Edit :name', ['name' => $role->name]) }}"
                                        class="btn btn-ghost btn-icon btn-sm max-sm:min-h-10 max-sm:w-10"
                                    >
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                @endcan
                                @can('delete', $role)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.roles.destroy', $role) }}"
                                        data-confirm-delete
                                        data-confirm-title="{{ __('Delete this role?') }}"
                                        data-confirm-text="{{ __('Only a role that no login uses can be deleted. This cannot be undone.') }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="{{ __('Delete') }}"
                                            aria-label="{{ __('Delete :name', ['name' => $role->name]) }}"
                                            class="btn btn-ghost btn-icon btn-sm text-slate-500 hover:bg-red-50 hover:text-red-600 max-sm:min-h-10 max-sm:w-10"
                                        >
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="5" icon="shield">
                        {{ __('No roles yet.') }}
                        @can('create', \App\Models\Role::class)
                            <x-slot:action>
                                <x-admin.button :href="route('admin.roles.create')" icon="plus" size="sm">{{ __('Add the first role') }}</x-admin.button>
                            </x-slot:action>
                        @endcan
                    </x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 flex gap-2 text-xs leading-5 text-slate-500">
        <x-admin.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
        <span>{{ __('Built-in roles can’t be deleted, and the administrator role can’t be changed. A role can only be deleted when no login uses it.') }}</span>
    </p>
@endsection
