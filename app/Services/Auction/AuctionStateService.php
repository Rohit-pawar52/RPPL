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
 *
 * public() is the same picture for the website: names, photos, roles, points
 * and squads only — no internal ids, no override flags, no tehsil, district
 * or age — and when the auction hides live bids, the standing bid, the
 * leading team and the bid list are left out of the data itself, not merely
 * hidden on screen.
 */
class AuctionStateService
{
    private const RECENT_BIDS = 8;

    /**
     * One colour per team, so the room can follow who is bidding at a glance. They are told apart on both the light
     * page and the dark projector, avoid red / green (they mean LIVE and SOLD there) and are handed out in the order
     * the teams joined the season, so a team keeps its colour for the whole auction.
     */
    private const TEAM_COLORS = [
        '#2563eb', '#e11d48', '#0d9488', '#7c3aed', '#ea580c', '#0891b2',
        '#c026d3', '#ca8a04', '#4f46e5', '#be123c', '#65a30d', '#475569',
    ];

    /** @var array<int, array<string, string>> team name => colour, per auction */
    private array $teamColorCache = [];

    /**
     * How long after a sale the website keeps announcing it with SOLD.
     */
    private const SOLD_BANNER_SECONDS = 25;

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
            'pool_check' => $auction->isCompleted() ? ['missing' => [], 'stale' => []] : $this->auctions->poolCheck($auction),
            'lot' => $lot ? $this->lot($auction, $lot) : null,
            'teams' => $teams,
            'waiting' => $this->waiting($auction),
            'sold' => $this->sold($auction),
        ];
    }

    /**
     * The auction the website shows: a live or paused one (newest season
     * first), otherwise the most recently completed one. A draft is never
     * public.
     */
    public function publicAuction(): ?Auction
    {
        $running = Auction::query()
            ->with('edition')
            ->whereIn('status', [Auction::STATUS_LIVE, Auction::STATUS_PAUSED])
            ->get()
            ->sortByDesc(fn (Auction $auction) => $auction->edition->year)
            ->first();

        return $running ?? Auction::query()
            ->with('edition')
            ->where('status', Auction::STATUS_COMPLETED)
            ->get()
            ->sortByDesc(fn (Auction $auction) => $auction->completed_at)
            ->first();
    }

    /**
     * What the homepage card needs, or null when it should not be shown:
     * while an auction is live or paused (with the player on the block, if
     * any), and for a week after it is completed.
     *
     * @return array{status: string, edition: string, name: ?string}|null
     */
    public function homeCard(): ?array
    {
        $auction = $this->publicAuction();

        if (! $auction) {
            return null;
        }

        if ($auction->isCompleted()) {
            return $auction->completed_at && $auction->completed_at->gt(now()->subDays(7))
                ? ['status' => $auction->status, 'edition' => $auction->edition->name, 'name' => null]
                : null;
        }

        $lot = $auction->current_lot_id
            ? AuctionLot::query()->with('playerRegistration.player')->find($auction->current_lot_id)
            : null;

        return [
            'status' => $auction->status,
            'edition' => $auction->edition->name,
            'name' => $lot?->isLive() ? $lot->playerRegistration->player->name : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function public(Auction $auction): array
    {
        $auction = $auction->fresh('edition');
        $showBids = $auction->show_live_bids;

        $lot = $auction->current_lot_id
            ? AuctionLot::query()->with('playerRegistration.player', 'leadingTeam.team')->find($auction->current_lot_id)
            : null;
        $lot = $lot?->isLive() ? $lot : null;

        $sales = $this->publicSales($auction, $showBids);
        $counts = $this->auctions->counts($auction);

        return [
            'auction' => [
                'status' => $auction->status,
                'round' => $auction->round,
                'edition' => $auction->edition->name,
                'show_live_bids' => $showBids,
                'min_bid' => $auction->min_bid,
                'max_squad' => $auction->max_squad,
                'min_squad' => $auction->min_squad,
            ],
            'counts' => [
                'total' => $counts['total'],
                'waiting' => $counts['pending'],
                'hold' => $counts['hold'],
                'sold' => $counts['sold'],
                'unsold' => $counts['unsold'],
            ],
            'stats' => $this->publicStats($sales),
            'lot' => $lot ? $this->publicLot($auction, $lot, $showBids) : null,
            'last_sale' => ($sales[0] ?? null) && $sales[0]['seconds_ago'] <= self::SOLD_BANNER_SECONDS ? $sales[0] : null,
            'sales' => $sales,
            'upcoming' => $this->publicWaiting($auction, AuctionLot::PENDING, true),
            'hold' => $this->publicWaiting($auction, AuctionLot::HOLD, false),
            'unsold' => $this->publicWaiting($auction, AuctionLot::UNSOLD, false),
            'teams' => $this->publicTeams($auction),
            'results' => $auction->isCompleted() ? $this->results($auction) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicLot(Auction $auction, AuctionLot $lot, bool $showBids): array
    {
        $registration = $lot->playerRegistration;
        $hasBid = $lot->current_bid !== null;

        return [
            'key' => $lot->id,
            'round' => $lot->round,
            ...$this->publicPerson($registration),
            'stats' => $this->pastStats($registration->player),
            'base_price' => $auction->min_bid,
            'bids_hidden' => ! $showBids,
            'current_bid' => $showBids ? $lot->current_bid : null,
            'leading_team' => $showBids && $hasBid && $lot->leadingTeam ? $lot->leadingTeam->team->name : null,
            'leading_color' => $showBids && $hasBid && $lot->leadingTeam ? $this->colorOf($auction, $lot->leadingTeam->team->name) : null,
            ...$this->publicBidding($auction, $lot, $showBids),
        ];
    }

    /**
     * The bidding on the player on the block: every team that has bid (its best bid, how many it made, its colour)
     * and the latest bids, newest first. Empty when the auction hides live bids.
     *
     * @return array{bids: list<array<string, mixed>>, bidders: list<array<string, mixed>>, bid_count: int}
     */
    private function publicBidding(Auction $auction, AuctionLot $lot, bool $showBids): array
    {
        if (! $showBids) {
            return ['bids' => [], 'bidders' => [], 'bid_count' => 0];
        }

        $bids = $lot->bids()->standing()->with('editionTeam.team')->latest('id')->get();

        $bidders = $bids
            ->groupBy('edition_team_id')
            ->map(fn ($teamBids) => [
                'team' => $teamBids->first()->editionTeam->team->name,
                'color' => $this->colorOf($auction, $teamBids->first()->editionTeam->team->name),
                'logo' => media_url($teamBids->first()->editionTeam->team->logo_path),
                'top' => (int) $teamBids->max('amount'),
                'count' => $teamBids->count(),
            ])
            ->sortByDesc('top')
            ->values()
            ->all();

        return [
            'bids' => $bids->take(self::RECENT_BIDS)->map(fn (AuctionBid $bid) => [
                'team' => $bid->editionTeam->team->name,
                'color' => $this->colorOf($auction, $bid->editionTeam->team->name),
                'amount' => $bid->amount,
            ])->values()->all(),
            'bidders' => $bidders,
            'bid_count' => $bids->count(),
        ];
    }

    /**
     * The colour of a team (by name) in this auction.
     */
    private function colorOf(Auction $auction, ?string $teamName): ?string
    {
        return $teamName === null ? null : ($this->teamColors($auction)[$teamName] ?? null);
    }

    /**
     * The colour of every team of this auction, by team name: the one the admin chose for the team, otherwise the next
     * colour of the palette that no team has chosen (so an automatic colour never copies a chosen one).
     *
     * @return array<string, string>
     */
    public function teamColors(Auction $auction): array
    {
        if (! isset($this->teamColorCache[$auction->id])) {
            $teams = EditionTeam::query()
                ->where('edition_id', $auction->edition_id)
                ->with('team')
                ->orderBy('id')
                ->get();

            $chosen = $teams->map(fn (EditionTeam $team) => $this->validColor($team->team->color))->filter()->values()->all();
            $free = array_values(array_filter(self::TEAM_COLORS, fn (string $color) => ! in_array($color, $chosen, true)));
            $pool = $free !== [] ? $free : self::TEAM_COLORS;

            $colors = [];
            $next = 0;

            foreach ($teams as $team) {
                $colors[$team->team->name] = $this->validColor($team->team->color) ?? $pool[$next++ % count($pool)];
            }

            $this->teamColorCache[$auction->id] = $colors;
        }

        return $this->teamColorCache[$auction->id];
    }

    private function validColor(?string $color): ?string
    {
        return $color !== null && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtolower($color) : null;
    }

    /**
     * What may be shown about a player on the website — never the internal
     * registration id.
     *
     * @return array<string, mixed>
     */
    /**
     * The village the player gave when registering, shown beside the name so two people with the same name can be
     * told apart (null when none was given).
     */
    private function villageOf(PlayerRegistration $registration): ?string
    {
        return filled($registration->village) ? trim($registration->village) : null;
    }

    /**
     * How many of each role a squad has (batter / bowler / all-rounder / wicket keeper), so the room can see what a
     * team still lacks. Players with no role set are not counted.
     *
     * @param  Collection<int, TeamPlayer>  $teamPlayers
     * @return array<string, int>
     */
    private function roleCounts(Collection $teamPlayers): array
    {
        $counts = array_fill_keys(array_keys(Player::PRIMARY_ROLE_LABELS), 0);

        foreach ($teamPlayers as $teamPlayer) {
            $role = $teamPlayer->playerRegistration?->player?->primary_role;

            if ($role !== null && array_key_exists($role, $counts)) {
                $counts[$role]++;
            }
        }

        return $counts;
    }

    private function publicPerson(PlayerRegistration $registration): array
    {
        return array_diff_key($this->person($registration), ['registration_id' => true, 'role_key' => true]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicTeams(Auction $auction): array
    {
        $standings = $this->auctions->teamStandings($auction);

        $squads = TeamPlayer::query()
            ->whereIn('edition_team_id', $standings->pluck('edition_team.id'))
            ->with('playerRegistration.player')
            ->get()
            ->groupBy('edition_team_id');

        return $standings->map(function (array $row) use ($auction, $squads) {
            /** @var EditionTeam $team */
            $team = $row['edition_team'];

            return [
                'name' => $team->team->name,
                'short_name' => $team->team->short_name,
                'logo' => media_url($team->team->logo_path),
                'color' => $this->colorOf($auction, $team->team->name),
                'purse' => $row['purse'],
                'spent' => $row['spent'],
                'left' => $row['left'],
                'count' => $row['count'],
                'still_needed' => $row['still_needed'],
                'roles' => $this->roleCounts($squads[$team->id] ?? collect()),
                'players' => ($squads[$team->id] ?? collect())
                    ->map(fn (TeamPlayer $teamPlayer) => [
                        'name' => $teamPlayer->playerRegistration->player->name,
                        'village' => $this->villageOf($teamPlayer->playerRegistration),
                        'role' => $teamPlayer->playerRegistration->player->primary_role
                            ? (Player::PRIMARY_ROLE_LABELS[$teamPlayer->playerRegistration->player->primary_role] ?? null)
                            : null,
                        'amount' => $teamPlayer->sold_amount === null ? null : (int) round((float) $teamPlayer->sold_amount),
                    ])
                    ->sortByDesc(fn (array $player) => $player['amount'] ?? -1)
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * Every player sold so far, newest first, with what they went for and -
     * only when the auction shows live bids - how many bids it took.
     *
     * @return list<array<string, mixed>>
     */
    private function publicSales(Auction $auction, bool $showBids): array
    {
        return $auction->lots()
            ->where('status', AuctionLot::SOLD)
            ->with('playerRegistration.player', 'teamPlayer.editionTeam.team')
            ->withCount(['bids as standing_bids_count' => fn ($query) => $query->standing()])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AuctionLot $lot) => [
                'key' => $lot->id,
                'name' => $lot->playerRegistration->player->name,
                'village' => $this->villageOf($lot->playerRegistration),
                'role' => $this->roleLabel($lot->playerRegistration->player),
                'team' => $lot->teamPlayer?->editionTeam->team->name,
                'team_color' => $this->colorOf($auction, $lot->teamPlayer?->editionTeam->team->name),
                'amount' => $this->soldAmount($lot),
                'bids' => $showBids ? (int) $lot->standing_bids_count : null,
                'seconds_ago' => $lot->sold_at ? (int) $lot->sold_at->diffInSeconds(now(), true) : null,
            ])
            ->all();
    }

    /**
     * What a sold player went for. The squad is the single source of truth (a
     * price corrected on the squad page counts); the winning bid is only the
     * fallback.
     */
    private function soldAmount(AuctionLot $lot): int
    {
        $squadPrice = $lot->teamPlayer?->sold_amount;

        return $squadPrice !== null ? (int) round((float) $squadPrice) : (int) $lot->current_bid;
    }

    /**
     * The headline numbers of the sales so far.
     *
     * @param  list<array<string, mixed>>  $sales
     * @return array{points_spent: int, average: int, highest: array{name: string, team: ?string, amount: int}|null}
     */
    private function publicStats(array $sales): array
    {
        $sales = collect($sales);
        $top = $sales->sortByDesc('amount')->first();

        return [
            'points_spent' => (int) $sales->sum('amount'),
            'average' => $sales->isEmpty() ? 0 : (int) round($sales->sum('amount') / $sales->count()),
            'highest' => $top ? ['name' => $top['name'], 'team' => $top['team'], 'amount' => (int) $top['amount']] : null,
        ];
    }

    /**
     * Players in one state (still to come, on hold, unsold), by name. Only
     * the "still to come" list carries their playing role.
     *
     * @return list<string>|list<array{name: string, role: ?string}>
     */
    private function publicWaiting(Auction $auction, string $status, bool $withRole): array
    {
        $players = $auction->lots()
            ->where('status', $status)
            ->with('playerRegistration.player')
            ->get()
            ->map(fn (AuctionLot $lot) => $lot->playerRegistration->player)
            ->sortBy(fn (Player $player) => mb_strtolower($player->name))
            ->values();

        return $withRole
            ? $players->map(fn (Player $player) => ['name' => $player->name, 'role' => $this->roleLabel($player)])->all()
            : $players->map(fn (Player $player) => $player->name)->all();
    }

    /**
     * How the bidding on one sold player went (the standing bids, lowest
     * first), or null when it must not be shown: live bids are off, or the
     * player is not sold in this auction.
     *
     * @return list<array{team: string, amount: int}>|null
     */
    public function publicSaleBids(Auction $auction, AuctionLot $lot): ?array
    {
        if (! $auction->show_live_bids || $lot->auction_id !== $auction->id || $lot->status !== AuctionLot::SOLD) {
            return null;
        }

        return $lot->bids()
            ->standing()
            ->with('editionTeam.team')
            ->orderBy('id')
            ->get()
            ->map(fn (AuctionBid $bid) => [
                'team' => $bid->editionTeam->team->name,
                'color' => $this->colorOf($auction, $bid->editionTeam->team->name),
                'amount' => $bid->amount,
            ])
            ->all();
    }

    private function roleLabel(Player $player): ?string
    {
        return $player->primary_role ? (Player::PRIMARY_ROLE_LABELS[$player->primary_role] ?? null) : null;
    }

    /**
     * The summary shown once the auction is over.
     *
     * @return array<string, mixed>
     */
    private function results(Auction $auction): array
    {
        $sold = $auction->lots()
            ->where('status', AuctionLot::SOLD)
            ->with('playerRegistration.player', 'teamPlayer.editionTeam.team')
            ->get();

        return [
            'sold_count' => $sold->count(),
            'points_spent' => (int) $sold->sum(fn (AuctionLot $lot) => $this->soldAmount($lot)),
            'top_buys' => $sold
                ->sortByDesc(fn (AuctionLot $lot) => $this->soldAmount($lot))
                ->take(5)
                ->map(fn (AuctionLot $lot) => [
                    'name' => $lot->playerRegistration->player->name,
                    'village' => $this->villageOf($lot->playerRegistration),
                    'team' => $lot->teamPlayer?->editionTeam->team->name,
                    'team_color' => $this->colorOf($auction, $lot->teamPlayer?->editionTeam->team->name),
                    'amount' => $this->soldAmount($lot),
                ])
                ->values()
                ->all(),
            'unsold' => $auction->lots()
                ->where('status', AuctionLot::UNSOLD)
                ->with('playerRegistration.player')
                ->get()
                ->map(fn (AuctionLot $lot) => $lot->playerRegistration->player->name)
                ->sort()
                ->values()
                ->all(),
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

        return [
            'registration_id' => $registration->id,
            'role_key' => $player->primary_role,
            'name' => $player->name,
            // The player's public photo - never the private one submitted with the registration.
            'photo' => media_url($player->photo_path, 'user'),
            'role' => $player->primary_role ? (Player::PRIMARY_ROLE_LABELS[$player->primary_role] ?? $player->primary_role) : null,
            'batting' => $player->batting_style ? ucfirst(str_replace('_', '-', $player->batting_style)) : null,
            'bowling' => $player->bowling_style && $player->bowling_style !== 'none' ? ucfirst(str_replace('_', ' ', $player->bowling_style)) : null,
            'village' => $this->villageOf($registration),
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

        return $standings->map(function (array $row) use ($auction, $lot, $next, $squads) {
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
                'color' => $this->colorOf($auction, $team->team->name),
                'roles' => $this->roleCounts($squads[$team->id] ?? collect()),
                'name' => $team->team->name,
                'short_name' => $team->team->short_name,
                'logo' => media_url($team->team->logo_path),
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
     * Every sale so far, newest first, for the console's "Sold players" panel
     * where any of them can be reopened or taken back. `locked` is true once
     * the player has played a match (the sale can no longer be undone) and
     * `orphan` when the squad row is already gone.
     *
     * @return list<array<string, mixed>>
     */
    private function sold(Auction $auction): array
    {
        /** @var Collection<int, AuctionLot> $lots */
        $lots = $auction->lots()
            ->where('status', AuctionLot::SOLD)
            ->with([
                'playerRegistration.player',
                'teamPlayer.editionTeam.team',
                'teamPlayer' => fn ($query) => $query->withExists('matchPlayers'),
            ])
            ->withCount(['bids as standing_bids_count' => fn ($query) => $query->standing()])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get();

        return $lots->values()->map(fn (AuctionLot $lot, int $index) => [
            'lot_id' => $lot->id,
            'number' => $lots->count() - $index,
            'name' => $lot->playerRegistration->player->name,
            'village' => $this->villageOf($lot->playerRegistration),
            'team' => $lot->teamPlayer?->editionTeam->team->name,
            'amount' => $this->soldAmount($lot),
            'bids' => (int) $lot->standing_bids_count,
            'at' => $lot->sold_at ? display_datetime($lot->sold_at, 'h:i A') : null,
            'locked' => (bool) $lot->teamPlayer?->match_players_exists,
            'orphan' => $lot->teamPlayer === null,
        ])->all();
    }
}
