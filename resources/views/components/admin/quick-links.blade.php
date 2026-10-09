@props(['layout' => 'menu'])

{{--
    The most common "create something" tasks, as links to the existing create
    pages - only those the signed-in user may use (each is a permission, the
    same Gate the page itself checks) and whose route exists.

      layout="menu"   the "+ New" dropdown in the top bar
      layout="grid"   a grid of tiles (the dashboard)
      layout="json"   a <script type="application/json"> the Ctrl+K palette reads

    Nothing is rendered when the user may do none of them (e.g. a scorer).
--}}
@php
    $catalog = [
        __('Tournament') => [
            ['label' => __('New match'), 'hint' => __('Schedule a fixture'), 'route' => 'admin.matches.create', 'can' => 'matches.manage', 'icon' => 'trophy'],
            ['label' => __('New registration'), 'hint' => __('Add a player entry by hand'), 'route' => 'admin.player-registrations.create', 'can' => 'registrations.manage', 'icon' => 'clipboard'],
            ['label' => __('Import players'), 'hint' => __('Upload a sheet of entries'), 'route' => 'admin.player-registrations.import', 'can' => 'registrations.manage', 'icon' => 'upload'],
            ['label' => __('New player'), 'hint' => __('Add to the player pool'), 'route' => 'admin.players.create', 'can' => 'players.manage', 'icon' => 'user'],
            ['label' => __('New team'), 'hint' => __('Create a team'), 'route' => 'admin.teams.create', 'can' => 'teams.manage', 'icon' => 'shield'],
        ],
        __('Finance') => [
            ['label' => __('Record transaction'), 'hint' => __('Income or expense'), 'route' => 'admin.edition-transactions.create', 'can' => 'finance.manage', 'icon' => 'currency'],
            ['label' => __('Add contribution'), 'hint' => __('A contributor\'s donation'), 'route' => 'admin.edition-contributions.create', 'can' => 'finance.manage', 'icon' => 'star'],
        ],
        __('Website') => [
            ['label' => __('New news post'), 'hint' => __('Publish an update'), 'route' => 'admin.news.create', 'can' => 'news.manage', 'icon' => 'newspaper'],
            ['label' => __('New announcement'), 'hint' => __('Show a notice on the site'), 'route' => 'admin.announcements.create', 'can' => 'announcements.manage', 'icon' => 'megaphone'],
            ['label' => __('Upload photos'), 'hint' => __('Add to the gallery'), 'route' => 'admin.photos.create', 'can' => 'photos.manage', 'icon' => 'camera'],
            ['label' => __('Send notification'), 'hint' => __('Push to followers'), 'route' => 'admin.notifications.create', 'can' => 'notifications.manage', 'icon' => 'bell'],
        ],
    ];

    $user = auth()->user();
    $groups = [];
    foreach ($catalog as $group => $items) {
        foreach ($items as $item) {
            if (\Illuminate\Support\Facades\Route::has($item['route']) && $user && $user->can($item['can'])) {
                $groups[$group][] = $item + ['url' => route($item['route'])];
            }
        }
    }
    $flat = collect($groups)->flatten(1)->values();
@endphp

@if($flat->isNotEmpty())
    @if($layout === 'json')
        <script type="application/json" id="admin-quick-actions">@json($flat->map(fn ($item) => ['label' => $item['label'], 'hint' => $item['hint'], 'url' => $item['url']])->all())</script>
    @elseif($layout === 'grid')
        <div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4']) }}>
            @foreach($flat->take(8) as $item)
                <a href="{{ $item['url'] }}" class="group flex items-center gap-3 rounded-xl border border-line bg-white p-3.5 shadow-card transition duration-150 hover:-translate-y-px hover:border-brand/40 hover:shadow-raised motion-reduce:transition-none">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand transition group-hover:bg-brand group-hover:text-brand-fg">
                        <x-admin.icon :name="$item['icon']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[13px] font-semibold leading-4 text-slate-900">{{ $item['label'] }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-slate-500 max-sm:hidden">{{ $item['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <details class="relative" data-dropdown>
            <summary class="btn btn-primary btn-sm cursor-pointer max-sm:min-h-10 max-sm:w-10 max-sm:px-0" aria-label="{{ __('Create new') }}">
                <x-admin.icon name="plus" class="h-4 w-4" />
                <span class="max-sm:hidden">{{ __('New') }}</span>
            </summary>
            <div class="adm-menu right-0 w-64 max-w-[calc(100vw-1.5rem)]">
                @foreach($groups as $group => $items)
                    <p class="adm-menu-label">{{ $group }}</p>
                    @foreach($items as $item)
                        <a href="{{ $item['url'] }}" class="adm-menu-item">
                            <x-admin.icon :name="$item['icon']" class="adm-menu-ico" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $item['label'] }}</span>
                            </span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </details>
    @endif
@endif
