<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionLot;
use Illuminate\Support\Collection;

/**
 * The "ready for the auction day?" check on the auction page: everything that would embarrass the auctioneer in
 * the hall, found beforehand and shown as green / amber / red lines. Read-only; it changes nothing.
 *
 * Levels: 'error' = fix this before starting, 'warn' = worth a look (the auction still works), 'ok' = fine.
 */
class AuctionReadinessService
{
    public function __construct(private readonly AuctionService $auctions, private readonly AuctionStateService $states) {}

    /**
     * @return array{items: list<array{level: string, title: string, detail: ?string}>, errors: int, warnings: int}
     */
    public function check(Auction $auction): array
    {
        $standings = $this->auctions->teamStandings($auction);
        $teams = $standings->count();

        $lots = $auction->lots()
            ->with(['playerRegistration.player'])
            ->get();

        // Players who still have to be auctioned, and everyone in the pool.
        $open = $lots->filter(fn (AuctionLot $lot) => in_array($lot->status, [AuctionLot::PENDING, AuctionLot::HOLD, AuctionLot::LIVE], true));

        $items = [];

        // ----- Teams and numbers -----
        $items[] = $teams >= 2
            ? $this->ok(__(':count teams are in this season.', ['count' => $teams]))
            : $this->error(__('At least two teams are needed.'), __('Add the teams on the season page before the auction.'));

        $needed = $teams * $auction->min_squad;
        $items[] = $lots->count() >= $needed
            ? $this->ok(__(':players players for :teams teams (each needs at least :min).', ['players' => $lots->count(), 'teams' => $teams, 'min' => $auction->min_squad]))
            : $this->error(
                __('Not enough players: :players in the pool, :needed needed.', ['players' => $lots->count(), 'needed' => $needed]),
                __(':teams teams each need at least :min players.', ['teams' => $teams, 'min' => $auction->min_squad]),
            );

        $poor = $standings->filter(fn (array $row) => $row['purse'] < $auction->min_bid * $auction->min_squad);
        $items[] = $poor->isEmpty()
            ? $this->ok(__('Every purse is enough for a minimum squad at the minimum bid.'))
            : $this->error(
                __('A purse is too small to reach a minimum squad.'),
                $poor->map(fn (array $row) => $row['edition_team']->team->name.' ('.points($row['purse']).')')->implode(', '),
            );

        // ----- The pool follows the payments -----
        $pool = $this->auctions->poolCheck($auction);
        $items[] = $pool['missing'] === [] && $pool['stale'] === []
            ? $this->ok(__('The player pool is up to date with the payments.'))
            : $this->warn(
                __('The player pool is out of date.'),
                __('Use "Update the pool" on this page. Missing: :missing. No longer eligible: :stale.', [
                    'missing' => $this->names($pool['missing']) ?: '—',
                    'stale' => $this->names($pool['stale']) ?: '—',
                ]),
            );

        // ----- What the room and the website will show -----
        $items[] = $this->completeness(
            $open->filter(fn (AuctionLot $lot) => blank($lot->playerRegistration->village)),
            __('Every waiting player has a village.'),
            __(':count waiting players have no village.'),
            __('The village is how two players with the same name are told apart in the hall.'),
        );

        $items[] = $this->completeness(
            $open->filter(fn (AuctionLot $lot) => blank($lot->playerRegistration->player->primary_role)),
            __('Every waiting player has a role.'),
            __(':count waiting players have no role.'),
            __('The role is shown on the block and used to call "the next batter / bowler".'),
        );

        $items[] = $this->completeness(
            $open->filter(fn (AuctionLot $lot) => blank($lot->playerRegistration->player->photo_path)),
            __('Every waiting player has a photo.'),
            __(':count waiting players have no photo.'),
            __('A default picture is shown for them.'),
            true,
        );

        $namesakes = $open
            ->groupBy(fn (AuctionLot $lot) => mb_strtolower(trim($lot->playerRegistration->player->name)))
            ->filter(fn (Collection $group) => $group->count() > 1);
        $items[] = $namesakes->isEmpty()
            ? $this->ok(__('No two waiting players share a name.'))
            : $this->warn(
                __(':count names belong to more than one waiting player.', ['count' => $namesakes->count()]),
                $namesakes->map(fn (Collection $group) => $group->first()->playerRegistration->player->name.' ('.$group->map(fn (AuctionLot $lot) => $lot->playerRegistration->village ?: '—')->implode(' / ').')')->implode(', '),
            );

        $noLogo = $standings->filter(fn (array $row) => blank($row['edition_team']->team->logo_path));
        $items[] = $noLogo->isEmpty()
            ? $this->ok(__('Every team has a logo.'))
            : $this->warn(__(':count teams have no logo.', ['count' => $noLogo->count()]), $noLogo->map(fn (array $row) => $row['edition_team']->team->name)->implode(', '));

        $alike = $this->alikeColors($auction);
        $items[] = $alike === []
            ? $this->ok(__('Every team has a colour of its own.'))
            : $this->warn(
                __(':count pairs of teams have colours that look alike.', ['count' => count($alike)]),
                implode(', ', $alike).'. '.__("Change a team's colour on its Edit page (Teams) so the room can tell them apart."),
            );

        $items[] = $auction->show_live_bids
            ? $this->ok(__('Live bids are shown on the website.'))
            : $this->warn(__('Live bids are hidden on the website.'), __('Visitors see who is on the block but not the bids. Change it in the rules below if you want them shown.'));

        return [
            'items' => $items,
            'errors' => count(array_filter($items, fn (array $item) => $item['level'] === 'error')),
            'warnings' => count(array_filter($items, fn (array $item) => $item['level'] === 'warn')),
        ];
    }

    /**
     * @param  Collection<int, AuctionLot>  $missing
     * @return array{level: string, title: string, detail: ?string}
     */
    private function completeness(Collection $missing, string $okTitle, string $warnTitle, string $detail, bool $soft = false): array
    {
        if ($missing->isEmpty()) {
            return $this->ok($okTitle);
        }

        $names = $missing->take(6)->map(fn (AuctionLot $lot) => $lot->playerRegistration->player->name)->implode(', ');
        $more = $missing->count() > 6 ? ' '.__('and :count more', ['count' => $missing->count() - 6]) : '';

        return $this->warn(str_replace(':count', (string) $missing->count(), $warnTitle), $detail.' '.$names.$more);
    }

    /**
     * Pairs of teams whose colours are hard to tell apart on a screen ("A and B").
     *
     * @return list<string>
     */
    private function alikeColors(Auction $auction): array
    {
        $colors = $this->states->teamColors($auction);
        $names = array_keys($colors);
        $rgb = fn (string $hex) => [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $pairs = [];

        foreach ($names as $i => $first) {
            foreach (array_slice($names, $i + 1) as $second) {
                [$r1, $g1, $b1] = $rgb($colors[$first]);
                [$r2, $g2, $b2] = $rgb($colors[$second]);

                if (sqrt(($r1 - $r2) ** 2 + ($g1 - $g2) ** 2 + ($b1 - $b2) ** 2) < 60) {
                    $pairs[] = $first.' & '.$second;
                }
            }
        }

        return $pairs;
    }

    /**
     * @param  list<string>  $names
     */
    private function names(array $names): string
    {
        return implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? ' …' : '');
    }

    /**
     * @return array{level: string, title: string, detail: ?string}
     */
    private function ok(string $title, ?string $detail = null): array
    {
        return ['level' => 'ok', 'title' => $title, 'detail' => $detail];
    }

    /**
     * @return array{level: string, title: string, detail: ?string}
     */
    private function warn(string $title, ?string $detail = null): array
    {
        return ['level' => 'warn', 'title' => $title, 'detail' => $detail];
    }

    /**
     * @return array{level: string, title: string, detail: ?string}
     */
    private function error(string $title, ?string $detail = null): array
    {
        return ['level' => 'error', 'title' => $title, 'detail' => $detail];
    }
}
