{{-- Shared by create.blade.php and edit.blade.php.
     $role      the role being edited, null on create
     $groups    the permissions a role can be given, by group (App\Support\Permissions, delegable only)
     $held      the permission keys the role holds now (empty on create)
     The page's <form> carries id="role-form"; the script below only looks inside it. --}}
@php
    $role = $role ?? null;
    $usersCount = $role?->users_count ?? 0;

    // After a failed save show what was ticked, not what is stored (and nothing if nothing was ticked).
    $ticked = session()->hasOldInput() ? (array) old('permissions', []) : ($held ?? []);
@endphp

<x-form.input
    name="name"
    label="Name"
    :value="$role->name ?? ''"
    maxlength="100"
    required
    autofocus
    help="Shown when you choose a role for a login, e.g. News Editor."
/>

@if($role)
    <p class="-mt-2 mb-3.5 text-[11px] text-slate-400">
        Code: <span class="font-mono">{{ $role->slug }}</span> &mdash; made from the name when the role was created and never changes.
        @if($role->isSystem())
            This is a built-in role: it can be renamed and its permissions changed, but it cannot be deleted.
        @endif
    </p>
@endif

<div class="mb-2 mt-1">
    <h2 class="text-[13px] font-semibold text-slate-800">Permissions</h2>
    <p class="mt-0.5 text-[11px] text-slate-400">
        Tick what a login with this role may do. A new role starts with nothing. &ldquo;Manage&rdquo; includes &ldquo;view&rdquo;.
        Whoever is to use the admin panel needs &ldquo;Sign in to the admin panel&rdquo;. Managing logins and roles is never given to a role: only administrators can do that.
    </p>
</div>

@if($errors->has('permissions') || $errors->has('permissions.*'))
    <p class="mb-2 text-xs text-red-600">{{ $errors->first('permissions') ?: $errors->first('permissions.*') }}</p>
@endif

@if($usersCount > 0)
    <div id="role-signin-warning" hidden class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        {{ $usersCount === 1 ? '1 user has' : $usersCount.' users have' }} this role. Without &ldquo;Sign in to the admin panel&rdquo;
        {{ $usersCount === 1 ? 'that user' : 'those users' }} will no longer be able to sign in.
    </div>
@endif

<div class="mb-4 grid gap-3 md:grid-cols-2">
    @foreach($groups as $group => $items)
        <fieldset data-group class="min-w-0 rounded-lg border border-slate-200 bg-white p-3">
            <legend class="px-1 text-[12px] font-semibold text-slate-800">{{ $group }}</legend>

            <label class="mb-2 inline-flex cursor-pointer items-center gap-2 text-[11px] font-medium text-slate-500">
                <input type="checkbox" data-select-group class="h-4 w-4 rounded border-slate-300 text-green-600 focus:ring-2 focus:ring-green-500" />
                Select all
            </label>

            <div class="space-y-1.5">
                @foreach($items as $key => $meta)
                    <label class="flex cursor-pointer items-start gap-2 text-[13px] text-slate-700" title="{{ $key }}">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $key }}"
                            data-permission="{{ $key }}"
                            @checked(in_array($key, $ticked, true))
                            class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-green-600 focus:ring-2 focus:ring-green-500"
                        />
                        <span>{{ $meta['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>

{{-- Ticking "manage" also ticks the matching "view" (and clearing "view" clears "manage"), each group's
     "Select all" follows its boxes, and the sign-in warning shows while "Sign in to the admin panel" is
     unticked. The server enforces all of this regardless (Permissions::normalize), so without JavaScript
     the form still works. --}}
<script>
    (function () {
        var form = document.getElementById('role-form');
        if (!form) { return; }

        var groups = form.querySelectorAll('[data-group]');
        var warning = document.getElementById('role-signin-warning');
        var panel = form.querySelector('input[data-permission="panel.access"]');

        var find = function (key) {
            return form.querySelector('input[data-permission="' + key + '"]');
        };

        var refreshGroup = function (group) {
            var all = group.querySelectorAll('input[data-permission]');
            var on = group.querySelectorAll('input[data-permission]:checked');
            var master = group.querySelector('input[data-select-group]');
            master.checked = all.length > 0 && on.length === all.length;
            master.indeterminate = on.length > 0 && on.length < all.length;
        };

        var refresh = function () {
            groups.forEach(refreshGroup);
            if (warning && panel) { warning.hidden = panel.checked; }
        };

        form.addEventListener('change', function (event) {
            var box = event.target;

            if (box.matches('input[data-select-group]')) {
                box.closest('[data-group]').querySelectorAll('input[data-permission]').forEach(function (item) {
                    item.checked = box.checked;
                });
            } else if (box.matches('input[data-permission]')) {
                var key = box.getAttribute('data-permission');

                if (box.checked && key.slice(-7) === '.manage') {
                    var view = find(key.slice(0, -7) + '.view');
                    if (view) { view.checked = true; }
                }

                if (!box.checked && key.slice(-5) === '.view') {
                    var manage = find(key.slice(0, -5) + '.manage');
                    if (manage) { manage.checked = false; }
                }
            }

            refresh();
        });

        refresh();
    })();
</script>
