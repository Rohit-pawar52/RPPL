<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Resolves config/admin_navigation.php for one user and request: drops
 * entries/groups the user's Gates refuse (or whose route no longer
 * exists), and marks what is active by ROUTE NAME so create/edit/show
 * pages and query strings keep their module highlighted.
 */
class AdminNavigation
{
    /**
     * @return array{top: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function forUser(?User $user): array
    {
        $config = config('admin_navigation');

        $top = collect($config['top'])
            ->filter(fn (array $item) => $this->visible($item, $user))
            ->map(fn (array $item) => $this->resolveItem($item))
            ->values()
            ->all();

        $groups = collect($config['groups'])
            ->filter(fn (array $group) => $this->visible($group, $user))
            ->map(function (array $group) use ($user) {
                $items = collect($group['items'])
                    ->filter(fn (array $item) => $this->visible($item, $user))
                    ->map(fn (array $item) => $this->resolveItem($item))
                    ->values()
                    ->all();

                // The config keeps the English text; it is translated here, per request, in the signed-in user's language.
                $group['label'] = __($group['label']);
                $group['items'] = $items;
                $group['active'] = collect($items)->contains('active', true);

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();

        return ['top' => $top, 'groups' => $groups];
    }

    /**
     * Breadcrumb trail for the current page, e.g. ['Content Management',
     * 'Videos'] — empty when the page is not part of the navigation.
     *
     * @param  array{top: list<array<string, mixed>>, groups: list<array<string, mixed>>}  $navigation
     * @return array{group: string|null, item: string}|null
     */
    public function current(array $navigation): ?array
    {
        foreach ($navigation['top'] as $item) {
            if ($item['active']) {
                return ['group' => null, 'item' => $item['label']];
            }
        }

        foreach ($navigation['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if ($item['active']) {
                    return ['group' => $group['label'], 'item' => $item['label']];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function visible(array $entry, ?User $user): bool
    {
        if (isset($entry['route']) && ! Route::has($entry['route'])) {
            return false;
        }

        $ability = $entry['ability'] ?? null;

        if ($ability === null) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        // A policy ability and the model it is asked about: ['viewAny', Role::class].
        if (is_array($ability)) {
            return $user->can(...$ability);
        }

        // Otherwise a Gate name, e.g. 'score-matches'.
        return $user->can($ability);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolveItem(array $item): array
    {
        $item['label'] = __($item['label']);
        $item['url'] = route($item['route']);
        $item['active'] = request()->routeIs(...$item['active']);

        return $item;
    }
}
