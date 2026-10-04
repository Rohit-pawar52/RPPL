<?php

/*
 * Admin sidebar structure — the ONE place navigation is defined. The
 * sidebar, the topbar breadcrumb and the active states are all derived
 * from this file (see App\Support\AdminNavigation), so adding or moving a
 * page never means editing Blade.
 *
 * Active state is decided by ROUTE NAME patterns (request()->routeIs), so
 * create/edit/show pages and query strings keep their module highlighted.
 *
 * 'ability' is a Gate name; an entry (or a whole group) is only shown when
 * the signed-in user passes it. Backend policies remain the real security
 * boundary — this only avoids showing links that would be refused.
 *
 * 'top' entries sit above the groups; each group is a "Management" section
 * that expands to its pages (the group holding the current page opens
 * automatically).
 */
return [
    'top' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'active' => ['admin.dashboard']],
        // Admin and scorer run matches; the auctioneer does not.
        ['label' => 'Matches', 'route' => 'admin.matches.index', 'icon' => 'trophy', 'active' => ['admin.matches.*'], 'ability' => 'score-matches'],
        // Admin and auctioneer run the player auction.
        ['label' => 'Auction', 'route' => 'admin.auctions.index', 'icon' => 'gavel', 'active' => ['admin.auctions.*'], 'ability' => 'run-auction'],
        ['label' => 'Reports', 'route' => 'admin.reports.index', 'icon' => 'document-chart', 'active' => ['admin.reports.*'], 'ability' => 'manage-tournament'],
        ['label' => 'Analytics', 'route' => 'admin.analytics.index', 'icon' => 'chart-bar', 'active' => ['admin.analytics.*'], 'ability' => 'manage-tournament'],
    ],

    'groups' => [
        [
            'key' => 'tournament',
            'label' => 'Tournament Management',
            'icon' => 'calendar',
            'ability' => 'manage-tournament',
            'items' => [
                // A season is opened from here (the Edition hub); its teams,
                // squads, matches and registrations are reached from the hub's
                // cards, so they have no sidebar entries of their own. The
                // older Edition Teams / Squads pages still work and highlight
                // Editions. Teams and Venues are the reusable master records.
                ['label' => 'Editions', 'route' => 'admin.editions.index', 'icon' => 'calendar', 'active' => ['admin.editions.*', 'admin.edition-teams.*', 'admin.team-players.*']],
                ['label' => 'Teams', 'route' => 'admin.teams.index', 'icon' => 'shield', 'active' => ['admin.teams.*']],
                ['label' => 'Venues', 'route' => 'admin.venues.index', 'icon' => 'map-pin', 'active' => ['admin.venues.*']],
            ],
        ],
        [
            'key' => 'players',
            'label' => 'Player Management',
            'icon' => 'user',
            'ability' => 'manage-tournament',
            'items' => [
                ['label' => 'Players', 'route' => 'admin.players.index', 'icon' => 'user', 'active' => ['admin.players.*']],
                ['label' => 'Registrations', 'route' => 'admin.player-registrations.index', 'icon' => 'clipboard', 'active' => ['admin.player-registrations.*']],
            ],
        ],
        [
            'key' => 'content',
            'label' => 'Content Management',
            'icon' => 'newspaper',
            'ability' => 'manage-tournament',
            'items' => [
                ['label' => 'News', 'route' => 'admin.news.index', 'icon' => 'newspaper', 'active' => ['admin.news.*']],
                ['label' => 'Videos', 'route' => 'admin.videos.index', 'icon' => 'play', 'active' => ['admin.videos.*']],
                ['label' => 'Photos', 'route' => 'admin.photos.index', 'icon' => 'camera', 'active' => ['admin.photos.*']],
                ['label' => 'Advertisements', 'route' => 'admin.advertisements.index', 'icon' => 'megaphone', 'active' => ['admin.advertisements.*']],
                ['label' => 'Announcements', 'route' => 'admin.announcements.index', 'icon' => 'megaphone', 'active' => ['admin.announcements.*']],
                // One entry covers Rules and Rule Types (reached from the Rules page).
                ['label' => 'Rules & Regulations', 'route' => 'admin.rules.index', 'icon' => 'book', 'active' => ['admin.rules.*', 'admin.rule-types.*']],
                ['label' => 'Content Pages', 'route' => 'admin.content-pages.index', 'icon' => 'clipboard', 'active' => ['admin.content-pages.*']],
            ],
        ],
        [
            'key' => 'finance',
            'label' => 'Finance Management',
            'icon' => 'currency',
            'ability' => 'manage-tournament',
            'items' => [
                ['label' => 'Overview', 'route' => 'admin.finance.overview', 'icon' => 'chart-bar', 'active' => ['admin.finance.*']],
                ['label' => 'Transactions', 'route' => 'admin.edition-transactions.index', 'icon' => 'currency', 'active' => ['admin.edition-transactions.*']],
                ['label' => 'Contributions', 'route' => 'admin.edition-contributions.index', 'icon' => 'star', 'active' => ['admin.edition-contributions.*']],
                ['label' => 'Contributors', 'route' => 'admin.contributors.index', 'icon' => 'users', 'active' => ['admin.contributors.*']],
            ],
        ],
        [
            'key' => 'communication',
            'label' => 'Communication',
            'icon' => 'bell',
            'ability' => 'manage-tournament',
            'items' => [
                ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'icon' => 'bell', 'active' => ['admin.notifications.*']],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'System Management',
            'icon' => 'cog',
            'ability' => 'manage-tournament',
            'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user', 'active' => ['admin.users.*']],
                ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => ['admin.settings.*']],
                ['label' => 'Data Cleanup', 'route' => 'admin.data-cleanup.index', 'icon' => 'trash', 'active' => ['admin.data-cleanup.*']],
            ],
        ],
    ],
];
