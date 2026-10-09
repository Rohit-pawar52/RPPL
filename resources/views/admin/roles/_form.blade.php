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

<x-admin.card title="Role">
    <div class="max-w-xl">
        <x-form.input
            name="name"
            label="Name"
            :value="$role->name ?? ''"
            maxlength="100"
            required
            autofocus
            help="Shown when you choose a role for a login, e.g. News Editor."
        />
    </div>

    @if($role)
        <p class="text-[11px] leading-4 text-slate-500">
            Code: <span class="font-mono">{{ $role->slug }}</span> &mdash; made from the name when the role was created and never changes.
            @if($role->isSystem())
                This is a built-in role: it can be renamed and its permissions changed, but it cannot be deleted.
            @endif
        </p>
    @endif
</x-admin.card>

<section class="mt-6" aria-labelledby="perm-heading">
    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-3xl">
            <h2 id="perm-heading" class="text-base font-semibold tracking-tight text-slate-900">Permissions</h2>
            <p class="mt-0.5 text-xs leading-5 text-slate-500">
                Tick what a login with this role may do. A new role starts with nothing. &ldquo;Manage&rdquo; includes &ldquo;view&rdquo;.
                Whoever is to use the admin panel needs &ldquo;Sign in to the admin panel&rdquo;. Managing logins and roles is never given to a role: only administrators can do that.
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
            <button type="button" class="btn btn-ghost btn-sm" data-tick-all>Tick all</button>
            <button type="button" class="btn btn-ghost btn-sm" data-clear-all>Clear all</button>
        </div>
    </div>

    @if($errors->has('permissions') || $errors->has('permissions.*'))
        <p class="fld-error mb-2">{{ $errors->first('permissions') ?: $errors->first('permissions.*') }}</p>
    @endif

    @if($usersCount > 0)
        <div id="role-signin-warning" hidden class="mb-3 flex gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-800">
            <x-admin.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
                {{ $usersCount === 1 ? '1 user has' : $usersCount.' users have' }} this role. Without &ldquo;Sign in to the admin panel&rdquo;
                {{ $usersCount === 1 ? 'that user' : 'those users' }} will no longer be able to sign in.
            </p>
        </div>
    @endif

    <div class="gap-4 md:columns-2 [&>*]:mb-4 [&>*]:break-inside-avoid">
        @foreach($groups as $group => $items)
            <fieldset data-group class="adm-card min-w-0 overflow-hidden">
                <legend class="sr-only">{{ $group }}</legend>

                <div class="flex items-center justify-between gap-3 border-b border-line bg-slate-50/70 px-4 py-2.5">
                    <p class="flex items-center gap-2 text-[13px] font-semibold text-slate-900" aria-hidden="true">
                        {{ $group }}
                        <span data-group-count class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold tabular-nums text-slate-500 ring-1 ring-inset ring-line"></span>
                    </p>
                    <label class="fld-check min-h-0 text-[11px] font-semibold text-slate-500">
                        <input type="checkbox" data-select-group />
                        Select all
                    </label>
                </div>

                <div class="divide-y divide-line">
                    @foreach($items as $key => $meta)
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 px-4 py-2.5 text-[13px] leading-5 text-slate-700 transition-colors hover:bg-hover" title="{{ $key }}">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $key }}"
                                data-permission="{{ $key }}"
                                @checked(in_array($key, $ticked, true))
                                class="mt-0.5 h-[18px] w-[18px] shrink-0 cursor-pointer accent-brand"
                            />
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>
</section>

{{-- Ticking "manage" also ticks the matching "view" (and clearing "view" clears "manage"), each group's
     "Select all" and counter follow its boxes, and the sign-in warning shows while "Sign in to the admin
     panel" is unticked. The server enforces all of this regardless (Permissions::normalize), so without
     JavaScript the form still works. --}}
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
            var count = group.querySelector('[data-group-count]');
            master.checked = all.length > 0 && on.length === all.length;
            master.indeterminate = on.length > 0 && on.length < all.length;
            if (count) { count.textContent = on.length + ' / ' + all.length; }
        };

        var refresh = function () {
            groups.forEach(refreshGroup);
            if (warning && panel) { warning.hidden = panel.checked; }
        };

        var setAll = function (state) {
            form.querySelectorAll('input[data-permission]').forEach(function (item) { item.checked = state; });
            refresh();
        };

        var tickAll = form.querySelector('[data-tick-all]');
        var clearAll = form.querySelector('[data-clear-all]');
        if (tickAll) { tickAll.addEventListener('click', function () { setAll(true); }); }
        if (clearAll) { clearAll.addEventListener('click', function () { setAll(false); }); }

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
