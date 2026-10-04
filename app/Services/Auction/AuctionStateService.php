<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Statistics\PlayerStatisticsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the auction console needs to draw itself, as one plain array
 * (also what the console's JSON endpoints return after every action). It
 * only reads: the rules and the numbers come from AuctionService, so the
 * screen can never disagree with what the server will accept.
 *
 * The player on the block is described with what an auctioneer may show the
 * room — name, photo, role, batting / bowling hand, home village and past
 * RPPL stats. A player's mobile number, e-mail, date of birth and documents
 * are never part of this.
 */
class AuctionStateService
{
    private const RECENT_BIDS = 8;

    private const RECENT_SALES = 10;

    public function __construct(
        private readonly AuctionService $auctions,
        private readonly PlayerStatisticsService $statistics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function console(Auction $auction): array
    {
        $auction = $auction->fresh();
        $lot = $auction->current_lot_id
            ? AuctionLot::query()->with('playerRegistration.player', 'leadingTeam.team')->find($auction->current_lot_id)
            : null;
        $lot = $lot?->isLive() ? $lot : null;

        $teams = $this->teams($auction, $lot);

        return [
            'auction' => [
                'id' => $auction->id,
                'status' => $auction->status,
                'round' => $auction->round,
                'min_bid' => $auction->min_bid,
                'bid_step' => $auction->bid_step,
                'min_squad' => $auction->min_squad,
                'max_squad' => $auction->max_squad,
                'team_purse' => $auction->team_purse,
                'show_live_bids' => $auction->show_live_bids,
            ],
            'counts' => $this->auctions->counts($auction),
            'lot' => $lot ? $this->lot($auction, $lot) : null,
            'teams' => $teams,
            'waiting' => $this->waiting($auction),
            'sales' => $this->sales($auction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lot(Auction $auction, AuctionLot $lot): array
    {
        $registration = $lot->playerRegistration;
        $player = $registration->player;

        $next = $lot->current_bid === null ? $auction->min_bid : $lot->current_bid + $auction->bid_step;

        return [
            'id' => $lot->id,
            'version' => $lot->version,
            'round' => $lot->round,
            ...$this->person($registration),
            'age' => $registration->age,
            'tehsil' => $registration->tehsil,
            'district' => $registration->district,
            'stats' => $this->pastStats($player),
            'current_bid' => $lot->current_bid,
            'next_bid' => $next,
            'leading_team' => $lot->leadingTeam ? [
                'id' => $lot->leadingTeam->id,
                'name' => $lot->leadingTeam->team->name,
            ] : null,
            'bids' => $lot->bids()
                ->standing()
                ->with('editionTeam.team')
                ->latest('id')
                ->limit(self::RECENT_BIDS)
                ->get()
                ->map(fn (AuctionBid $bid) => [
                    'id' => $bid->id,
                    'team' => $bid->editionTeam->team->name,
                    'amount' => $bid->amount,
                    'override' => $bid->is_override,
                    'at' => display_datetime($bid->created_at, 'h:i:s A'),
                ])
                ->all(),
        ];
    }

    /**
     * The shared "who is this player" block: shown on the block and in the
     * lists. Deliberately the only place player details are chosen.
     *
     * @return array<string, mixed>
     */
    private function person(PlayerRegistration $registration): array
    {
        $player = $registration->player;
        $photo = $registration->photo_path ?: $player->photo_path;

        return [
            'registration_id' => $registration->id,
            'name' => $player->name,
            'photo' => $photo ? Storage::url($photo) : null,
            'role' => $player->primary_role ? (Player::PRIMARY_ROLE_LABELS[$player->primary_role] ?? $player->primary_role) : null,
            'batting' => $player->batting_style ? ucfirst(str_replace('_', '-', $player->batting_style)) : null,
            'bowling' => $player->bowling_style && $player->bowling_style !== 'none' ? ucfirst(str_replace('_', ' ', $player->bowling_style)) : null,
            'village' => $registration->village,
        ];
    }

    /**
     * Past RPPL record across every season, or null for a first-timer.
     *
     * @return array{matches: int, runs: int, highest: ?int, wickets: int, best_bowling: ?string}|null
     */
    private function pastStats(Player $player): ?array
    {
        $stats = $this->statistics->getPlayerStatistics($player);

        if ($stats['matches_played'] === 0) {
            return null;
        }

        return [
            'matches' => $stats['matches_played'],
            'runs' => (int) $stats['batting']['runs'],
            'highest' => $stats['batting']['highest_score'],
            'wickets' => (int) $stats['bowling']['wickets'],
            'best_bowling' => $stats['bowling']['best_bowling'],
        ];
    }

    /**
     * One entry per team: its purse position and what a tap on it would do
     * right now. `state` says why a team cannot simply be tapped:
     * 'leading' (already holds the bid), 'full' (squad full), 'purse' (the
     * next bid is more than it has left), 'reserve' (more than it can bid
     * and still reach the minimum squad — an override can allow it) or 'ok'.
     *
     * @return list<array<string, mixed>>
     */
    private function teams(Auction $auction, ?AuctionLot $lot): array
    {
        $standings = $this->auctions->teamStandings($auction);

        $squads = TeamPlayer::query()
            ->whereIn('edition_team_id', $standings->pluck('edition_team.id'))
            ->with('playerRegistration.player')
            ->get()
            ->groupBy('edition_team_id');

        $next = $lot ? ($lot->current_bid === null ? $auction->min_bid : $lot->current_bid + $auction->bid_step) : null;

        return $standings->map(function (array $row) use ($lot, $next, $squads) {
            /** @var EditionTeam $team */
            $team = $row['edition_team'];

            $state = match (true) {
                $lot === null => 'idle',
                $lot->leading_edition_team_id === $team->id => 'leading',
                $row['full'] => 'full',
                $next > $row['left'] => 'purse',
                $next > $row['max_bid'] => 'reserve',
                default => 'ok',
            };

            return [
                'id' => $team->id,
                'name' => $team->team->name,
                'short_name' => $team->team->short_name,
                'logo' => $team->team->logo_path ? Storage::url($team->team->logo_path) : null,
                'purse' => $row['purse'],
                'spent' => $row['spent'],
                'left' => $row['left'],
                'count' => $row['count'],
                'slots_left' => $row['slots_left'],
                'still_needed' => $row['still_needed'],
                'max_bid' => $row['max_bid'],
                'full' => $row['full'],
                'state' => $state,
                'players' => ($squads[$team->id] ?? collect())
                    ->map(fn (TeamPlayer $teamPlayer) => [
                        'name' => $teamPlayer->playerRegistration->player->name,
                        'amount' => $teamPlayer->sold_amount === null ? null : (int) round((float) $teamPlayer->sold_amount),
                    ])
                    ->sortByDesc(fn (array $player) => $player['amount'] ?? -1)
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * Players still to be called, for the search list: waiting and on hold.
     *
     * @return list<array<string, mixed>>
     */
    private function waiting(Auction $auction): array
    {
        return $auction->lots()
            ->whereIn('status', [AuctionLot::PENDING, AuctionLot::HOLD])
            ->with('playerRegistration.player')
            ->get()
            ->map(fn (AuctionLot $lot) => [
                'id' => $lot->id,
                'status' => $lot->status,
                'round' => $lot->round,
                ...$this->person($lot->playerRegistration),
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sales(Auction $auction): array
    {
        /** @var Collection<int, AuctionLot> $lots */
        $lots = $auction->lots()
            ->where('status', AuctionLot::SOLD)
            ->with('playerRegistration.player', 'teamPlayer.editionTeam.team')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_SALES)
            ->get();

        return $lots->map(fn (AuctionLot $lot) => [
            'lot_id' => $lot->id,
            'name' => $lot->playerRegistration->player->name,
            'team' => $lot->teamPlayer?->editionTeam->team->name,
            'amount' => $lot->current_bid,
            'at' => $lot->sold_at ? display_datetime($lot->sold_at, 'h:i A') : null,
        ])->all();
    }
}
