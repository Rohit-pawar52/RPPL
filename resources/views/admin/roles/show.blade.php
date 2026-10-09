@extends('layouts.admin')

@section('title', __('Role Details'))

@section('actions')
    <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary" icon="arrow-left">{{ __('Back to roles') }}</x-admin.button>
    @can('update', $role)
        <x-admin.button href="{{ route('admin.roles.edit', $role) }}" icon="pencil">{{ __('Edit') }}</x-admin.button>
    @endcan
@endsection

@section('content')
    @php
        // What the role really holds, by group (a stored "manage" also counts as "view").
        $granted = collect($groups)
            ->map(fn ($items) => collect($items)->filter(fn ($_, $key) => $role->hasPermission($key)))
            ->filter(fn ($items) => $items->isNotEmpty());
        $canSignIn = $role->hasPermission('panel.access');
    @endphp

    <div class="max-w-5xl space-y-6">
        <x-admin.card>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">{{ $role->name }}</h2>
                @if($role->isSystem())
                    @include('admin.roles._built-in-badge')
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-line pt-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Code') }}</dt>
                    <dd class="mt-0.5 font-mono text-[13px] font-semibold text-slate-900">{{ $role->slug }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Users with this role') }}</dt>
                    <dd class="mt-0.5 text-[13px] font-semibold text-slate-900">
                        @if($role->users_count > 0)
                            @can('viewAny', \App\Models\User::class)
                                <a href="{{ route('admin.users.index', ['role_id' => $role->id]) }}" class="text-link hover:text-link-hover hover:underline">{{ $role->users_count }}</a>
                            @else
                                {{ $role->users_count }}
                            @endcan
                        @else
                            0
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Permissions') }}</dt>
                    <dd class="mt-0.5 text-[13px] font-semibold text-slate-900">
                        {{ $role->isAdmin() ? __('Every permission') : $granted->sum(fn ($items) => $items->count()) }}
                    </dd>
                </div>
            </dl>
        </x-admin.card>

        @if($role->isAdmin())
            <p class="flex gap-2.5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-[13px] leading-5 text-sky-900">
                <x-admin.icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ __('Administrators always have every permission, including managing logins and roles. This role can’t be edited or deleted.') }}</span>
            </p>
        @else
            @if(! $canSignIn)
                <p class="flex gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                    <x-admin.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ __('This role can’t sign in to the admin panel, so its other permissions have no effect until “Sign in to the admin panel” is given.') }}</span>
                </p>
            @endif

            @if($granted->isEmpty())
                <div class="adm-card">
                    <x-admin.empty icon="shield">
                        {{ __('This role has no permissions yet.') }}
                        @can('update', $role)
                            <x-slot:action>
                                <x-admin.button :href="route('admin.roles.edit', $role)" icon="pencil" size="sm">{{ __('Choose permissions') }}</x-admin.button>
                            </x-slot:action>
                        @endcan
                    </x-admin.empty>
                </div>
            @else
                <div class="gap-4 md:columns-2 [&>*]:mb-4 [&>*]:break-inside-avoid">
                    @foreach($granted as $group => $items)
                        <div class="adm-card min-w-0 overflow-hidden">
                            <h3 class="border-b border-line bg-slate-50/70 px-4 py-2.5 text-[13px] font-semibold text-slate-900">{{ __($group) }}</h3>
                            <ul class="divide-y divide-line">
                                @foreach($items as $key => $meta)
                                    <li class="flex items-start gap-2.5 px-4 py-2.5 text-[13px] leading-5 text-slate-700" title="{{ $key }}">
                                        <x-admin.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                        <span>{{ __($meta['label']) }}</span>
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
