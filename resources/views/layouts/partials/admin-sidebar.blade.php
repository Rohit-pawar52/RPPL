{{-- Mobile-only backdrop behind the drawer; JS toggles the "hidden" class --}}
<div id="admin-backdrop" class="fixed inset-0 z-30 hidden bg-black/30 lg:hidden"></div>

<aside
    id="admin-sidebar"
    class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full border-r border-neutral-200 bg-white pt-14 transition-transform duration-150 lg:static lg:z-0 lg:w-56 lg:translate-x-0 lg:pt-0"
>
    <nav class="h-full space-y-1 overflow-y-auto px-3 py-4">
        <x-nav-item :route="route('admin.dashboard')" icon="home" :active="request()->routeIs('admin.dashboard')">
            Dashboard
        </x-nav-item>

        <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Matches</p>
        <x-nav-item :route="route('admin.matches.index')" icon="trophy" :active="request()->routeIs('admin.matches.*')">
            Matches
        </x-nav-item>

        @can('manage-tournament')
            <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Tournament</p>
            <x-nav-item :route="route('admin.editions.index')" icon="calendar" :active="request()->routeIs('admin.editions.*')">
                Editions
            </x-nav-item>
            <x-nav-item :route="route('admin.teams.index')" icon="shield" :active="request()->routeIs('admin.teams.*')">
                Teams
            </x-nav-item>
            <x-nav-item :route="route('admin.edition-teams.index')" icon="shield" :active="request()->routeIs('admin.edition-teams.*')">
                Edition Teams
            </x-nav-item>
            <x-nav-item :route="route('admin.players.index')" icon="user" :active="request()->routeIs('admin.players.*')">
                Players
            </x-nav-item>
            <x-nav-item :route="route('admin.player-registrations.index')" icon="clipboard" :active="request()->routeIs('admin.player-registrations.*')">
                Registrations
            </x-nav-item>
            <x-nav-item :route="route('admin.team-players.index')" icon="users" :active="request()->routeIs('admin.team-players.*')">
                Squads
            </x-nav-item>
            <x-nav-item :route="route('admin.venues.index')" icon="map-pin" :active="request()->routeIs('admin.venues.*')">
                Venues
            </x-nav-item>
            <x-nav-item :route="route('admin.edition-transactions.index')" icon="currency" :active="request()->routeIs('admin.edition-transactions.*')">
                Finance
            </x-nav-item>
            <x-nav-item
                :route="route('admin.committee-members.index')"
                icon="users"
                :active="request()->routeIs('admin.committee-members.*') || request()->routeIs('admin.edition-contributions.*')"
            >
                Committee
            </x-nav-item>
            <x-nav-item :route="route('admin.contributors.index')" icon="users" :active="request()->routeIs('admin.contributors.*')">
                Contributors
            </x-nav-item>

            <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">System</p>
            <x-nav-item :route="route('admin.users.index')" icon="user" :active="request()->routeIs('admin.users.*')">
                Users
            </x-nav-item>
            <x-nav-item :route="route('admin.notifications.index')" icon="bell" :active="request()->routeIs('admin.notifications.*')">
                Notifications
            </x-nav-item>
            <x-nav-item :route="route('admin.announcements.index')" icon="megaphone" :active="request()->routeIs('admin.announcements.*')">
                Announcements
            </x-nav-item>
            <x-nav-item :route="route('admin.reports.index')" icon="document-chart" :active="request()->routeIs('admin.reports.*')">
                Reports
            </x-nav-item>
            <x-nav-item :route="route('admin.data-cleanup.index')" icon="trash" :active="request()->routeIs('admin.data-cleanup.*')">
                Data Cleanup
            </x-nav-item>
            <x-nav-item :route="route('admin.settings.index')" icon="cog" :active="request()->routeIs('admin.settings.*')">
                Settings
            </x-nav-item>
        @endcan
    </nav>
</aside>
