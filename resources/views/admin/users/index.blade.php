@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search name, email&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="role_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.users.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <a
            href="{{ route('admin.users.create') }}"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
        >
            + New user
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="px-4 py-2 font-medium">Email</th>
                    <th class="px-4 py-2 font-medium">Role</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($users as $user)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.users.show', $user) }}" class="hover:underline">
                                {{ $user->name }}
                            </a>
                            @if($user->id === auth()->id())
                                <span class="ml-1 text-[10px] font-normal text-neutral-400">(you)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ $user->email }}</td>
                        <td class="px-4 py-2 text-neutral-600">{{ $user->role->name }}</td>
                        <td class="px-4 py-2"><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.users.show', $user) }}"
                                    title="View"
                                    aria-label="View {{ $user->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.users.edit', $user) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $user->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-neutral-400">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
@endsection
