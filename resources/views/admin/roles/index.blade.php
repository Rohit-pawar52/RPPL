@extends('layouts.admin')

@section('title', 'Roles')

@section('subtitle', 'What each kind of login is allowed to do. Administrators always have every permission.')

@section('actions')
    @can('create', \App\Models\Role::class)
        <x-admin.button href="{{ route('admin.roles.create') }}" variant="primary">+ New role</x-admin.button>
    @endcan
@endsection

@section('content')
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Code</th>
                    <th class="px-4 py-2 text-right font-medium">Users</th>
                    <th class="px-4 py-2 text-right font-medium">Permissions</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($roles as $role)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.roles.show', $role) }}" class="hover:underline">{{ $role->name }}</a>
                            @if($role->isSystem())
                                <span class="ml-1 align-middle">@include('admin.roles._built-in-badge')</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 font-mono text-[12px] text-slate-500 md:table-cell">{{ $role->slug }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">
                            @if($role->users_count > 0)
                                @can('viewAny', \App\Models\User::class)
                                    <a href="{{ route('admin.users.index', ['role_id' => $role->id]) }}" class="hover:underline">{{ $role->users_count }}</a>
                                @else
                                    {{ $role->users_count }}
                                @endcan
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">
                            {{ $role->isAdmin() ? 'All' : ($permissionCounts[$role->id] ?? 0) }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.roles.show', $role) }}"
                                    title="View"
                                    aria-label="View {{ $role->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                @can('update', $role)
                                    <a
                                        href="{{ route('admin.roles.edit', $role) }}"
                                        title="Edit"
                                        aria-label="Edit {{ $role->name }}"
                                        class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                    >
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                @endcan
                                @can('delete', $role)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.roles.destroy', $role) }}"
                                        data-confirm-delete
                                        data-confirm-title="Delete this role?"
                                        data-confirm-text="Only a role that no login uses can be deleted. This cannot be undone."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="Delete"
                                            aria-label="Delete {{ $role->name }}"
                                            class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="5">No roles yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-[11px] text-slate-400">
        Built-in roles can&rsquo;t be deleted, and the administrator role can&rsquo;t be changed. A role can only be deleted when no login uses it.
    </p>
@endsection
