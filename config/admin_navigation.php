<?php

use App\Models\Role;
use App\Models\User;

/*
 * Admin sidebar structure — the ONE place navigation is defined. The
 * sidebar, the topbar breadcrumb and the active states are all derived
 * from this file (see App\Support\AdminNavigation), so adding or moving a
 * page never means editing Blade.
 *
 * Active state is decided by ROUTE NAME patterns (request()->routeIs), so
 * create/edit/show pages and query strings keep their module highlighted.
 *
 * 'ability' is a Gate name - every permission key (App\Support\Permissions)
 * is one, e.g. 'news.view' - or a policy ability with the model it is asked
 * about (['viewAny', Role::class]); an entry is only shown when the
 * signed-in user passes it, and a group with no visible entry disappears.
 * Backend policies remain the real security boundary — this only avoids
 * showing links that would be refused.
 *
 * 'top' entries sit above the groups; each group is a "Management" section
 * that expands to its pages (the group holding the current page opens
 * automatically).
 */
return [
    'top' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'active' => ['admin.dashboard']],
        // Fixtures and match day: admin and scorer by default; the auctioneer does not.
        ['label' => 'Matches', 'route' => 'admin.matches.index', 'icon' => 'trophy', 'active' => ['admin.matches.*'], 'ability' => 'matches.view'],
        // Admin and auctioneer run the player auction.
        ['label' => 'Auction', 'route' => 'admin.auctions.index', 'icon' => 'gavel', 'active' => ['admin.auctions.*'], 'ability' => 'auction.run'],
        ['label' => 'Reports', 'route' => 'admin.reports.index', 'icon' => 'document-chart', 'active' => ['admin.reports.*'], 'ability' => 'reports.view'],
        ['label' => 'Analytics', 'route' => 'admin.analytics.index', 'icon' => 'chart-bar', 'active' => ['admin.analytics.*'], 'ability' => 'analytics.view'],
    ],

    'groups' => [
        [
            'key' => 'tournament',
            'label' => 'Tournament Management',
            'icon' => 'calendar',
            'items' => [
                // A season is opened from here (the Edition hub); its teams,
                // squads, matches and registrations are reached from the hub's
                // cards, so they have no sidebar entries of their own. The
                // older Edition Teams / Squads pages still work and highlight
                // Editions. Teams and Venues are the reusable master records.
                ['label' => 'Editions', 'route' => 'admin.editions.index', 'icon' => 'calendar', 'active' => ['admin.editions.*', 'admin.edition-teams.*', 'admin.team-players.*'], 'ability' => 'editions.view'],
                ['label' => 'Teams', 'route' => 'admin.teams.index', 'icon' => 'shield', 'active' => ['admin.teams.*'], 'ability' => 'teams.view'],
                ['label' => 'Venues', 'route' => 'admin.venues.index', 'icon' => 'map-pin', 'active' => ['admin.venues.*'], 'ability' => 'venues.view'],
            ],
        ],
        [
            'key' => 'players',
            'label' => 'Player Management',
            'icon' => 'user',
            'items' => [
                ['label' => 'Players', 'route' => 'admin.players.index', 'icon' => 'user', 'active' => ['admin.players.*'], 'ability' => 'players.view'],
                ['label' => 'Registrations', 'route' => 'admin.player-registrations.index', 'icon' => 'clipboard', 'active' => ['admin.player-registrations.*'], 'ability' => 'registrations.view'],
            ],
        ],
        [
            'key' => 'content',
            'label' => 'Content Management',
            'icon' => 'newspaper',
            'items' => [
                ['label' => 'News', 'route' => 'admin.news.index', 'icon' => 'newspaper', 'active' => ['admin.news.*'], 'ability' => 'news.view'],
                ['label' => 'Videos', 'route' => 'admin.videos.index', 'icon' => 'play', 'active' => ['admin.videos.*'], 'ability' => 'videos.view'],
                ['label' => 'Photos', 'route' => 'admin.photos.index', 'icon' => 'camera', 'active' => ['admin.photos.*'], 'ability' => 'photos.view'],
                ['label' => 'Advertisements', 'route' => 'admin.advertisements.index', 'icon' => 'megaphone', 'active' => ['admin.advertisements.*'], 'ability' => 'advertisements.view'],
                ['label' => 'Announcements', 'route' => 'admin.announcements.index', 'icon' => 'megaphone', 'active' => ['admin.announcements.*'], 'ability' => 'announcements.view'],
                // One entry covers Rules and Rule Types (reached from the Rules page).
                ['label' => 'Rules & Regulations', 'route' => 'admin.rules.index', 'icon' => 'book', 'active' => ['admin.rules.*', 'admin.rule-types.*'], 'ability' => 'rules.view'],
                ['label' => 'Content Pages', 'route' => 'admin.content-pages.index', 'icon' => 'clipboard', 'active' => ['admin.content-pages.*'], 'ability' => 'content_pages.manage'],
            ],
        ],
        [
            'key' => 'finance',
            'label' => 'Finance Management',
            'icon' => 'currency',
            'items' => [
                ['label' => 'Overview', 'route' => 'admin.finance.overview', 'icon' => 'chart-bar', 'active' => ['admin.finance.*'], 'ability' => 'finance.view'],
                ['label' => 'Transactions', 'route' => 'admin.edition-transactions.index', 'icon' => 'currency', 'active' => ['admin.edition-transactions.*'], 'ability' => 'finance.view'],
                ['label' => 'Contributions', 'route' => 'admin.edition-contributions.index', 'icon' => 'star', 'active' => ['admin.edition-contributions.*'], 'ability' => 'finance.view'],
                ['label' => 'Contributors', 'route' => 'admin.contributors.index', 'icon' => 'users', 'active' => ['admin.contributors.*'], 'ability' => 'contributors.view'],
            ],
        ],
        [
            'key' => 'communication',
            'label' => 'Communication',
            'icon' => 'bell',
            'items' => [
                ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'icon' => 'bell', 'active' => ['admin.notifications.*'], 'ability' => 'notifications.view'],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'System Management',
            'icon' => 'cog',
            'items' => [
                // Access control: each link asks its own policy (users.view / roles.view,
                // which only administrators can ever hold).
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user', 'active' => ['admin.users.*'], 'ability' => ['viewAny', User::class]],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'shield', 'active' => ['admin.roles.*'], 'ability' => ['viewAny', Role::class]],
                ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => ['admin.settings.*'], 'ability' => 'settings.manage'],
                ['label' => 'Data Cleanup', 'route' => 'admin.data-cleanup.index', 'icon' => 'trash', 'active' => ['admin.data-cleanup.*'], 'ability' => 'data_cleanup.manage'],
            ],
        ],
    ],
];
