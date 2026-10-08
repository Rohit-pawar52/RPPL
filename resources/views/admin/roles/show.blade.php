@extends('layouts.admin')

@section('title', 'Role Details')

@section('content')
    @php
        // What the role really holds, by group (a stored "manage" also counts as "view").
        $granted = collect($groups)
            ->map(fn ($items) => collect($items)->filter(fn ($_, $key) => $role->hasPermission($key)))
            ->filter(fn ($items) => $items->isNotEmpty());
        $canSignIn = $role->hasPermission('panel.access');
    @endphp

    <div class="mb-4 flex max-w-4xl items-center justify-between">
        <a href="{{ route('admin.roles.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to roles
        </a>
        @can('update', $role)
            <a
                href="{{ route('admin.roles.edit', $role) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
            >
                <x-icon name="pencil" class="h-3.5 w-3.5" />
                Edit
            </a>
        @endcan
    </div>

    <div class="max-w-4xl rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900">{{ $role->name }}</h2>
            @if($role->isSystem())
                @include('admin.roles._built-in-badge')
            @endif
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-slate-400">Code</dt>
                <dd class="mt-0.5 font-mono font-medium text-slate-800">{{ $role->slug }}</dd>
            </div>
            <div>
                <dt class="text-slate-400">Users with this role</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    @if($role->users_count > 0)
                        @can('viewAny', \App\Models\User::class)
                            <a href="{{ route('admin.users.index', ['role_id' => $role->id]) }}" class="hover:underline">{{ $role->users_count }}</a>
                        @else
                            {{ $role->users_count }}
                        @endcan
                    @else
                        0
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-400">Permissions</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ $role->isAdmin() ? 'Every permission' : $granted->sum(fn ($items) => $items->count()) }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 max-w-4xl">
        @if($role->isAdmin())
            <p class="rounded-md border border-blue-100 bg-blue-50 p-3 text-[12px] text-blue-900">
                Administrators always have every permission, including managing logins and roles. This role can&rsquo;t be edited or deleted.
            </p>
        @else
            @if(! $canSignIn)
                <p class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This role can&rsquo;t sign in to the admin panel, so its other permissions have no effect until &ldquo;Sign in to the admin panel&rdquo; is given.
                </p>
            @endif

            @if($granted->isEmpty())
                <p class="rounded-md border border-slate-200 bg-white p-4 text-[13px] text-slate-500">
                    This role has no permissions yet.
                </p>
            @else
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach($granted as $group => $items)
                        <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-3">
                            <h3 class="mb-2 text-[12px] font-semibold text-slate-800">{{ $group }}</h3>
                            <ul class="space-y-1.5">
                                @foreach($items as $key => $meta)
                                    <li class="flex items-start gap-2 text-[13px] text-slate-700" title="{{ $key }}">
                                        <span class="mt-0.5 text-green-600" aria-hidden="true">&#10003;</span>
                                        <span>{{ $meta['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection
