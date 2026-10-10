<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\Player;
use App\Models\TeamPlayer;

/**
 * The auction as rows for a spreadsheet (CSV opens in Excel) or the results PDF: the result, every squad, every
 * bid and the activity log. Also the safety copy of an auction - the whole story outside the database. Read-only.
 * Column headings are English on purpose, like every export.
 */
class AuctionExportService
{
    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}
     */
    public function results(Auction $auction): array
    {
        $lots = $auction->lots()
            ->where('status', AuctionLot::SOLD)
            ->with('playerRegistration.player', 'teamPlayer.editionTeam.team')
            ->withCount(['bids as standing_bids_count' => fn ($query) => $query->standing()])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($lots as $index => $lot) {
            $rows[] = [
                $index + 1,
                $lot->playerRegistration->player->name,
                $lot->playerRegistration->village,
                $this->role($lot->playerRegistration->player),
                $lot->teamPlayer?->editionTeam->team->name,
                $lot->teamPlayer?->sold_amount !== null ? (int) round((float) $lot->teamPlayer->sold_amount) : $lot->current_bid,
                $lot->standing_bids_count,
                $lot->sold_at ? display_datetime($lot->sold_at, 'd M Y, h:i A') : null,
            ];
        }

        return ['headers' => ['No', 'Player', 'Village', 'Role', 'Team', 'Price (points)', 'Bids', 'Sold at'], 'rows' => $rows];
    }

    /**
     * Every team with its players and what it spent, for the results PDF.
     *
     * @return list<array{name: string, purse: int, spent: int, left: int, players: list<array{name: string, village: ?string, role: ?string, amount: ?int}>}>
     */
    public function squads(Auction $auction, AuctionService $auctions): array
    {
        $standings = $auctions->teamStandings($auction);

        $squads = TeamPlayer::query()
            ->whereIn('edition_team_id', $standings->pluck('edition_team.id'))
            ->with('playerRegistration.player')
            ->get()
            ->groupBy('edition_team_id');

        return $standings->map(fn (array $row) => [
            'name' => $row['edition_team']->team->name,
            'purse' => (int) $row['purse'],
            'spent' => (int) $row['spent'],
            'left' => (int) $row['left'],
            'players' => ($squads[$row['edition_team']->id] ?? collect())
                ->map(fn (TeamPlayer $teamPlayer) => [
                    'name' => $teamPlayer->playerRegistration->player->name,
                    'village' => $teamPlayer->playerRegistration->village,
                    'role' => $this->role($teamPlayer->playerRegistration->player),
                    'amount' => $teamPlayer->sold_amount !== null ? (int) round((float) $teamPlayer->sold_amount) : null,
                ])
                ->sortByDesc(fn (array $player) => $player['amount'] ?? -1)
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}
     */
    public function bids(Auction $auction): array
    {
        $bids = AuctionBid::query()
            ->whereIn('auction_lot_id', $auction->lots()->select('id'))
            ->with('lot.playerRegistration.player', 'editionTeam.team', 'user')
            ->orderBy('id')
            ->get();

        return [
            'headers' => ['Time', 'Player', 'Team', 'Bid (points)', 'Over the reserve limit', 'Undone at', 'By'],
            'rows' => $bids->map(fn (AuctionBid $bid) => [
                display_datetime($bid->created_at, 'd M Y, h:i:s A'),
                $bid->lot->playerRegistration->player->name,
                $bid->editionTeam->team->name,
                (int) $bid->amount,
                $bid->is_override ? 'Yes' : 'No',
                $bid->cancelled_at ? display_datetime($bid->cancelled_at, 'd M Y, h:i:s A') : null,
                $bid->user?->name,
            ])->all(),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}
     */
    public function events(Auction $auction): array
    {
        $events = $auction->events()->with('user')->orderBy('id')->get();

        return [
            'headers' => ['Time', 'By', 'What happened'],
            'rows' => $events->map(fn ($event) => [
                display_datetime($event->created_at, 'd M Y, h:i:s A'),
                $event->user?->name,
                $event->describe(),
            ])->all(),
        ];
    }

    private function role(Player $player): ?string
    {
        return $player->primary_role ? (Player::PRIMARY_ROLE_LABELS[$player->primary_role] ?? $player->primary_role) : null;
    }
}
