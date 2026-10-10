<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * The single list of everything a role can be allowed to do.
 *
 * Authorization is deny-by-default: a permission key that is not in this catalog is never granted
 * to anybody (not even an admin), and a role only holds what is stored for it in `role_permissions`.
 * Two exceptions are hard-wired in {@see Role::hasPermission()}: the `admin` role holds every
 * permission (so it can never be locked out by editing), and the access-control permissions
 * (`users.*`, `roles.*`) are never delegable - whoever may edit roles or create users could
 * otherwise hand themselves, or a friend, full control.
 *
 * Naming: `<module>.view` lets a role look, `<module>.manage` lets it create/update/delete (and
 * implies `.view`). Other verbs name one special capability.
 */
final class Permissions
{
    /**
     * Roles that always exist and whose default grants are what the app shipped with before roles
     * became editable. `admin` is not listed: it holds everything.
     */
    public const SYSTEM_ROLES = ['admin', 'scorer', 'auctioneer'];

    /**
     * Holding the key on the left also grants the keys on the right: running, scoring or finalizing a
     * match all happen on the match page, and sending a push notification happens on its page, so each
     * includes the matching `.view`. (`<module>.manage` includes `<module>.view` too, see implied().)
     *
     * @var array<string, list<string>>
     */
    private const IMPLIES = [
        'matches.run' => ['matches.view'],
        'scoring.score' => ['matches.view'],
        'matches.finalize' => ['matches.view'],
        'matches.reopen' => ['matches.view'],
        'notifications.send' => ['notifications.view'],
    ];

    /** @var array<string, list<string>>|null key => the keys whose holding grants it (itself included) */
    private static ?array $grantors = null;

    /**
     * group => [key => [label, delegable, default roles]].
     *
     * @var array<string, array<string, array{0: string, 1: bool, 2: list<string>}>>
     */
    private const CATALOG = [
        'Panel & dashboard' => [
            'panel.access' => ['Sign in to the admin panel', true, ['scorer', 'auctioneer']],
            'dashboard.tournament' => ['See the tournament dashboard (matches, teams, registrations)', true, ['scorer']],
        ],
        'Matches & scoring' => [
            'matches.view' => ['View fixtures and match details', true, ['scorer']],
            'matches.manage' => ['Create, edit, delete and cancel fixtures', true, []],
            'matches.run' => ['Run match day: toss, playing XI, start match and innings, abandon', true, ['scorer']],
            'scoring.score' => ['Score ball by ball (record and undo deliveries)', true, ['scorer']],
            'matches.finalize' => ['Finalize a match result', true, ['scorer']],
            'matches.reopen' => ['Reopen a finalized match', true, ['scorer']],
        ],
        'Tournament setup' => [
            'editions.view' => ['View editions and their teams', true, []],
            'editions.manage' => ['Manage editions and their teams', true, []],
            'teams.view' => ['View teams and squads', true, []],
            'teams.manage' => ['Manage teams and squads', true, []],
            'venues.view' => ['View venues', true, []],
            'venues.manage' => ['Manage venues', true, []],
            'players.view' => ['View players', true, []],
            'players.manage' => ['Manage players', true, []],
            'registrations.view' => ['View player registrations', true, []],
            'registrations.manage' => ['Review and manage player registrations', true, []],
        ],
        'Finance' => [
            'finance.view' => ['View finance (income, expenses, contributions) and payment figures', true, []],
            'finance.manage' => ['Manage transactions and contributions', true, []],
            'contributors.view' => ['View contributors', true, []],
            'contributors.manage' => ['Manage contributors', true, []],
            'committee.view' => ['View committee members', true, []],
            'committee.manage' => ['Manage committee members', true, []],
        ],
        'Website content' => [
            'news.view' => ['View news', true, []],
            'news.manage' => ['Manage news', true, []],
            'photos.view' => ['View photos', true, []],
            'photos.manage' => ['Manage photos', true, []],
            'videos.view' => ['View videos', true, []],
            'videos.manage' => ['Manage videos', true, []],
            'rules.view' => ['View rules and rule types', true, []],
            'rules.manage' => ['Manage rules and rule types', true, []],
            'announcements.view' => ['View announcements', true, []],
            'announcements.manage' => ['Manage announcements', true, []],
            'advertisements.view' => ['View sponsor advertisements', true, []],
            'advertisements.manage' => ['Manage sponsor advertisements', true, []],
            'content_pages.manage' => ['Edit Privacy Policy, Terms and FAQs pages', true, []],
            'notifications.view' => ['View push notifications', true, []],
            'notifications.manage' => ['Create and edit push notifications', true, []],
            'notifications.send' => ['Send push notifications', true, []],
        ],
        'Auction' => [
            'auction.run' => ['Run and manage the player auction', true, ['auctioneer']],
        ],
        'Reports & system' => [
            'reports.view' => ['View reports', true, []],
            'analytics.view' => ['View website analytics', true, []],
            'settings.manage' => ['Change global settings', true, []],
            'data_cleanup.manage' => ['Delete old data (data cleanup)', true, []],
        ],
        'Access control (admin only)' => [
            'users.view' => ['View login accounts', false, []],
            'users.manage' => ['Create and edit login accounts', false, []],
            'roles.view' => ['View roles and their permissions', false, []],
            'roles.manage' => ['Create, edit and delete roles', false, []],
        ],
    ];

    /**
     * @return array<string, array<string, array{label: string, delegable: bool}>> group => [key => meta]
     */
    public static function grouped(bool $delegableOnly = false): array
    {
        $out = [];

        foreach (self::CATALOG as $group => $items) {
            foreach ($items as $key => [$label, $delegable]) {
                if ($delegableOnly && ! $delegable) {
                    continue;
                }

                $out[$group][$key] = ['label' => $label, 'delegable' => $delegable];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function keys(bool $delegableOnly = false): array
    {
        return array_keys(array_merge(...array_values(self::grouped($delegableOnly))));
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function isDelegable(string $key): bool
    {
        return in_array($key, self::keys(delegableOnly: true), true);
    }

    /**
     * What a built-in role gets when it is created, matching what the app granted before roles were
     * editable. Roles not named here (including custom ones) start with nothing.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $slug): array
    {
        $keys = [];

        foreach (self::CATALOG as $items) {
            foreach ($items as $key => [, , $roles]) {
                if (in_array($slug, $roles, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * What holding `$key` also grants (not including itself): the module's `.view` for a
     * `.manage`, plus the explicit IMPLIES list.
     *
     * @return list<string>
     */
    public static function implied(string $key): array
    {
        $implied = self::IMPLIES[$key] ?? [];

        if (str_ends_with($key, '.manage') && self::exists($view = substr($key, 0, -7).'.view')) {
            $implied[] = $view;
        }

        return $implied;
    }

    /**
     * Every key whose holding grants `$key`: the key itself and anything that implies it.
     *
     * @return list<string>
     */
    public static function grantedBy(string $key): array
    {
        if (self::$grantors === null) {
            self::$grantors = [];

            foreach (self::keys() as $holder) {
                self::$grantors[$holder][] = $holder;

                foreach (self::implied($holder) as $granted) {
                    self::$grantors[$granted][] = $holder;
                }
            }
        }

        return self::$grantors[$key] ?? [];
    }

    /**
     * Keep only real, delegable keys, and add everything each of them implies (see implied()) so the
     * stored set never holds, say, a manage right without the matching view.
     *
     * @param  iterable<mixed>  $keys
     * @return list<string>
     */
    public static function normalize(iterable $keys): array
    {
        $allowed = self::keys(delegableOnly: true);
        $clean = [];

        foreach ($keys as $key) {
            if (is_string($key) && in_array($key, $allowed, true)) {
                $clean[$key] = true;
            }
        }

        foreach (array_keys($clean) as $key) {
            foreach (self::implied($key) as $implied) {
                if (in_array($implied, $allowed, true)) {
                    $clean[$implied] = true;
                }
            }
        }

        return array_keys($clean);
    }

    /**
     * Store the built-in defaults for a freshly created system role. Idempotent; a no-op for any
     * other slug.
     */
    public static function applyDefaults(Role $role): void
    {
        $keys = self::defaultsFor($role->slug);

        if ($keys === []) {
            return;
        }

        DB::table('role_permissions')->insertOrIgnore(array_map(
            fn (string $key) => ['role_id' => $role->id, 'permission' => $key],
            $keys
        ));
    }
}
