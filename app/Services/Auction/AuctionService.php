<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every rule of the player auction, in one place. The admin console and the
 * public page only ever call this and show what it returns.
 *
 * The money rules are strict; the flow is deliberately loose:
 *  - a bid is the next step above the standing one (or any bigger amount in
 *    steps), never below the minimum bid, never above what the team has
 *    left, and never from the team that is already leading;
 *  - a team keeps enough in hand to still reach the minimum squad (its "max
 *    bid") unless the admin / auctioneer overrides that one limit;
 *  - any pending or hold player can be called at any time, "next round" and
 *    "complete" are allowed with players still waiting, and settings may be
 *    changed at any moment (they apply from the next bid).
 *
 * Every action takes a row lock on the auction first, and the ones that
 * change the player on the block take the version the caller last saw, so
 * a tap made on an out-of-date screen is refused instead of applied. A bid
 * also carries an idempotency key, so a double tap places one bid.
 * A sale writes the squad row through TeamPlayerService — the squad is the
 * single source of truth for who plays for whom, and a team's spent points
 * are simply the total of its squad's sold amounts.
 */
class AuctionService
{
    public function __construct(
        private readonly TeamPlayerService $teamPlayers,
        private readonly PlayerRegistrationService $registrations,
    ) {}

    // ----- Setup ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $settings  any of Auction's rule settings
     */
    public function create(Edition $edition, ?User $by = null, array $settings = []): Auction
    {
        if ($edition->status === 'completed') {
            $this->fail('auction', 'This season is completed, so it cannot have a new auction.');
        }

        if ($edition->auction()->exists()) {
            $this->fail('auction', 'This season already has an auction.');
        }

        $settings = $this->onlySettings($settings);
        $this->assertSquadLimits((int) ($settings['min_squad'] ?? 12), (int) ($settings['max_squad'] ?? 15));

        return DB::transaction(function () use ($edition, $by, $settings) {
            // refresh() so the column defaults (purse, round, ...) are loaded.
            $auction = Auction::create([
                ...$settings,
                'edition_id' => $edition->id,
                'status' => Auction::STATUS_DRAFT,
                'created_by' => $by?->id,
            ])->refresh();

            $this->refreshPool($auction);

            return $auction;
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, int|null>  $teamPurses  edition team id => own purse (null / blank = use the default)
     */
    public function updateSettings(Auction $auction, array $settings, array $teamPurses = []): Auction
    {
        return DB::transaction(function () use ($auction, $settings, $teamPurses) {
            $auction = $this->lockAuction($auction);

            if ($auction->isCompleted()) {
                $this->fail('auction', 'This auction is completed, so its settings can no longer be changed.');
            }

            $settings = $this->onlySettings($settings);
            $this->assertSquadLimits((int) ($settings['min_squad'] ?? $auction->min_squad), (int) ($settings['max_squad'] ?? $auction->max_squad));

            $auction->update($settings);

            $teams = EditionTeam::query()->where('edition_id', $auction->edition_id)->whereIn('id', array_keys($teamPurses))->get();

            foreach ($teams as $team) {
                $team->update(['auction_purse' => filled($teamPurses[$team->id] ?? null) ? (int) $teamPurses[$team->id] : null]);
            }

            return $auction->refresh();
        });
    }

    /**
     * Brings the pool in line with the season's registrations: paid, active
     * players who are not in a squad yet and not in the auction are added as
     * pending; players still waiting (pending / hold) who are no longer
     * eligible — payment changed, deactivated, or already in a squad — are
     * taken out. Players who are live, sold or unsold are never touched.
     *
     * @return array{added: int, removed: int}
     */
    public function refreshPool(Auction $auction): array
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);

            if ($auction->isCompleted()) {
                $this->fail('auction', 'This auction is completed, so its player pool can no longer change.');
            }

            $eligible = $this->eligibleRegistrationIds($auction->edition_id);
            $existing = $auction->lots()->pluck('status', 'player_registration_id');

            $toAdd = $eligible->diff($existing->keys());

            foreach ($toAdd as $registrationId) {
                AuctionLot::create([
                    'auction_id' => $auction->id,
                    'player_registration_id' => $registrationId,
                    'status' => AuctionLot::PENDING,
                    'round' => $auction->round,
                ]);
            }

            $stale = $existing
                ->filter(fn (string $status) => in_array($status, [AuctionLot::PENDING, AuctionLot::HOLD], true))
                ->keys()
                ->diff($eligible);

            $removed = $stale->isEmpty() ? 0 : $auction->lots()->whereIn('player_registration_id', $stale->all())->delete();

            return ['added' => $toAdd->count(), 'removed' => $removed];
        });
    }

    /**
     * Someone who turns up on the day without having registered online: the
     * player (found by mobile number, never edited) and a paid registration
     * for this season are created, and they join the waiting players — to be
     * called like anyone else. The same door as "registered offline" on the
     * Squads page, minus putting them straight in a team.
     */
    public function addWalkInPlayer(Auction $auction, string $name, string $phone): AuctionLot
    {
        return DB::transaction(function () use ($auction, $name, $phone) {
            $auction = $this->lockAuction($auction);

            if ($auction->isCompleted()) {
                $this->fail('auction', 'This auction is completed, so no more players can be added.');
            }

            $edition = Edition::findOrFail($auction->edition_id);
            $normalised = Player::normalizePhone($phone);

            if ($normalised === null || strlen(preg_replace('/\D/', '', $normalised)) < 7) {
                $this->fail('phone', 'Enter the player\'s mobile number.');
            }

            $player = Player::where('phone', $normalised)->first();

            if ($player && ! $player->is_active) {
                $this->fail('phone', 'This player is inactive and cannot be added.');
            }

            if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
                $this->fail('phone', $player->name.' is already registered for this season. If they have paid, use "Update the pool" on the set-up page.');
            }

            $player ??= Player::create(['name' => $name, 'phone' => $normalised]);

            $registration = $this->registrations->createRegistration([
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => 'paid',
                'registration_fee' => $edition->registration_fee,
                'registered_at' => now(),
            ]);

            return AuctionLot::create([
                'auction_id' => $auction->id,
                'player_registration_id' => $registration->id,
                'status' => AuctionLot::PENDING,
                'round' => $auction->round,
            ]);
        });
    }

    /**
     * How many players are in each state, for the setup page and boards.
     *
     * @return array<string, int>
     */
    public function counts(Auction $auction): array
    {
        $byStatus = $auction->lots()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $counts = [];
        foreach (AuctionLot::STATUSES as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    // ----- Lifecycle --------------------------------------------------------

    public function start(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);

            if (! $auction->isDraft()) {
                $this->fail('auction', 'This auction has already been started.');
            }

            if (! EditionTeam::where('edition_id', $auction->edition_id)->exists()) {
                $this->fail('auction', 'Add the season\'s teams before starting the auction.');
            }

            if (! $auction->lots()->exists()) {
                $this->fail('auction', 'There are no paid players in the pool yet.');
            }

            $auction->update(['status' => Auction::STATUS_LIVE, 'started_at' => now()]);

            return $auction;
        });
    }

    public function pause(Auction $auction): Auction
    {
        return $this->transition($auction, Auction::STATUS_LIVE, Auction::STATUS_PAUSED, 'Only a live auction can be paused.');
    }

    public function resume(Auction $auction): Auction
    {
        return $this->transition($auction, Auction::STATUS_PAUSED, Auction::STATUS_LIVE, 'Only a paused auction can be resumed.');
    }

    /**
     * Hold players come back for another pass. Allowed at any moment (even
     * with players still pending) — it is the auctioneer's call, not a
     * rule. Returns how many players came back.
     */
    public function startNextRound(Auction $auction): int
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction);

            $round = $auction->round + 1;
            $auction->update(['round' => $round]);

            return $auction->lots()
                ->where('status', AuctionLot::HOLD)
                ->update(['status' => AuctionLot::PENDING, 'round' => $round, 'version' => DB::raw('version + 1')]);
        });
    }

    /**
     * Ends the auction. Anyone still waiting (pending, hold, or on the
     * block) becomes unsold. Returns who is left short of the minimum squad
     * so it can be fixed by hand afterwards.
     *
     * @return array{unsold: int, short_teams: Collection<int, array{edition_team: EditionTeam, count: int, missing: int}>}
     */
    public function complete(Auction $auction): array
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction);

            $live = $auction->current_lot_id ? AuctionLot::query()->whereKey($auction->current_lot_id)->lockForUpdate()->first() : null;

            if ($live?->isLive()) {
                $this->cancelStandingBids($live);
            }

            $unsold = $auction->lots()
                ->whereIn('status', [AuctionLot::PENDING, AuctionLot::HOLD, AuctionLot::LIVE])
                ->update([
                    'status' => AuctionLot::UNSOLD,
                    'current_bid' => null,
                    'leading_edition_team_id' => null,
                    'version' => DB::raw('version + 1'),
                ]);

            $auction->update(['status' => Auction::STATUS_COMPLETED, 'completed_at' => now(), 'current_lot_id' => null]);

            return ['unsold' => $unsold, 'short_teams' => $this->teamsShortOfMinimum($auction)];
        });
    }

    // ----- Teams ------------------------------------------------------------

    /**
     * Where every team of the season stands. `max_bid` is the most it may
     * bid on the next player: what is left, minus the minimum bid for each
     * further player it still needs to reach the minimum squad. It is null
     * when the squad is already full.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function teamStandings(Auction $auction): Collection
    {
        return EditionTeam::query()
            ->where('edition_id', $auction->edition_id)
            ->with('team')
            ->withCount('teamPlayers')
            ->withSum('teamPlayers as spent_points', 'sold_amount')
            ->get()
            ->map(fn (EditionTeam $team) => $this->standingFor($auction, $team))
            ->sortBy(fn (array $row) => mb_strtolower($row['edition_team']->team->name))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function teamStanding(Auction $auction, EditionTeam $editionTeam): array
    {
        $team = EditionTeam::query()
            ->whereKey($editionTeam->id)
            ->with('team')
            ->withCount('teamPlayers')
            ->withSum('teamPlayers as spent_points', 'sold_amount')
            ->firstOrFail();

        return $this->standingFor($auction, $team);
    }

    // ----- On the block -----------------------------------------------------

    /**
     * Puts a pending / hold player on the block. If someone else is there
     * without any bid they quietly go back; if they already have a bid, the
     * auctioneer must sell or hold them first (a standing bid is never
     * dropped silently).
     */
    public function callLot(Auction $auction, AuctionLot $lot): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot) {
            $auction = $this->lockAuction($auction);
            $this->assertLive($auction, 'call a player');
            $lot = $this->lockLot($auction, $lot);

            if ($lot->isLive() && $auction->current_lot_id === $lot->id) {
                return $lot;
            }

            if (! in_array($lot->status, [AuctionLot::PENDING, AuctionLot::HOLD], true)) {
                $this->fail('auction', 'Only a waiting or hold player can be called.');
            }

            $this->clearBlock($auction);

            $lot->update([
                'status' => AuctionLot::LIVE,
                'round' => $auction->round,
                'called_at' => now(),
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => $lot->id]);

            return $lot->refresh();
        });
    }

    /**
     * A random player from the pending ones, or null when nobody is left
     * waiting (the auctioneer can then start the next round).
     */
    public function callRandom(Auction $auction): ?AuctionLot
    {
        $pick = $auction->lots()->where('status', AuctionLot::PENDING)->inRandomOrder()->first();

        return $pick ? $this->callLot($auction, $pick) : null;
    }

    /**
     * @param  int|null  $amount  null = the next step; otherwise any amount in steps above the standing bid
     * @param  bool  $override  allow going past the "keep enough for the minimum squad" limit (never past what the team has)
     */
    public function placeBid(
        Auction $auction,
        AuctionLot $lot,
        EditionTeam $team,
        int $expectedVersion,
        ?int $amount = null,
        ?string $idempotencyKey = null,
        bool $override = false,
        ?User $by = null,
    ): AuctionBid {
        if ($idempotencyKey && ($previous = AuctionBid::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $previous;
        }

        return DB::transaction(function () use ($auction, $lot, $team, $expectedVersion, $amount, $idempotencyKey, $override, $by) {
            $auction = $this->lockAuction($auction);

            // A retry that raced the first request: it is already placed.
            if ($idempotencyKey && ($previous = AuctionBid::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $previous;
            }

            $this->assertRunning($auction, 'The auction is not live.');
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            if ($team->edition_id !== $auction->edition_id) {
                $this->fail('bid', 'That team is not in this season.');
            }

            $team->loadMissing('team');
            $name = $team->team->name;

            if ($lot->leading_edition_team_id === $team->id) {
                $this->fail('bid', $name.' is already leading — another team has to bid.');
            }

            $standing = $this->teamStanding($auction, $team);

            if ($standing['full']) {
                $this->fail('bid', $name.' already has a full squad of '.$auction->max_squad.'.');
            }

            $next = $this->nextBidAmount($auction, $lot);
            $amount ??= $next;

            if ($amount < $next) {
                $this->fail('bid', 'The bid must be at least '.points($next, true).'.');
            }

            if (($amount - $auction->min_bid) % $auction->bid_step !== 0) {
                $this->fail('bid', 'A bid goes up in steps of '.points($auction->bid_step).' (from '.points($auction->min_bid).').');
            }

            if ($amount > $standing['left']) {
                $this->fail('bid', $name.' has only '.points($standing['left'], true).' left.');
            }

            $overLimit = $amount > $standing['max_bid'];

            if ($overLimit && ! $override) {
                $this->fail('reserve', $name.' must keep '.points($standing['reserve'], true).' to still reach a squad of '.$auction->min_squad.' — the most it can bid now is '.points($standing['max_bid'], true).'.');
            }

            $bid = AuctionBid::create([
                'auction_lot_id' => $lot->id,
                'edition_team_id' => $team->id,
                'amount' => $amount,
                'user_id' => $by?->id,
                'idempotency_key' => $idempotencyKey,
                'is_override' => $overLimit,
            ]);

            $lot->update([
                'current_bid' => $amount,
                'leading_edition_team_id' => $team->id,
                'version' => $lot->version + 1,
            ]);

            return $bid;
        });
    }

    /**
     * Takes back the latest standing bid; the one before it (if any) stands
     * again. The undone bid stays in the history, marked cancelled.
     */
    public function undoBid(Auction $auction, AuctionLot $lot, int $expectedVersion): AuctionBid
    {
        return DB::transaction(function () use ($auction, $lot, $expectedVersion) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, 'The auction is not live.');
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            $latest = $lot->bids()->standing()->latest('id')->first();

            if (! $latest) {
                $this->fail('bid', 'There is no bid to undo.');
            }

            $latest->update(['cancelled_at' => now()]);

            $previous = $lot->bids()->standing()->latest('id')->first();

            $lot->update([
                'current_bid' => $previous?->amount,
                'leading_edition_team_id' => $previous?->edition_team_id,
                'version' => $lot->version + 1,
            ]);

            return $latest;
        });
    }

    /**
     * Sells the player on the block to the leading team at the standing bid
     * and puts them in that team's squad.
     */
    public function sell(Auction $auction, AuctionLot $lot, int $expectedVersion): TeamPlayer
    {
        return DB::transaction(function () use ($auction, $lot, $expectedVersion) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, 'The auction is not live.');
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            if ($lot->current_bid === null || $lot->leading_edition_team_id === null) {
                $this->fail('sell', 'There is no bid yet, so the player cannot be sold.');
            }

            $team = EditionTeam::findOrFail($lot->leading_edition_team_id);
            $standing = $this->teamStanding($auction, $team);

            // Re-checked at the moment of sale: the squad page may have been
            // used since the bid was placed.
            if ($standing['full']) {
                $this->fail('sell', $team->team->name.' already has a full squad of '.$auction->max_squad.'.');
            }

            if ($lot->current_bid > $standing['left']) {
                $this->fail('sell', $team->team->name.' has only '.points($standing['left'], true).' left, less than the bid.');
            }

            $registration = PlayerRegistration::findOrFail($lot->player_registration_id);

            if ($this->teamPlayers->addPlayers($team, [$registration->id => $lot->current_bid]) !== 1) {
                $this->fail('sell', 'This player is already in a squad or is inactive, so cannot be sold.');
            }

            $teamPlayer = TeamPlayer::query()->where('player_registration_id', $registration->id)->firstOrFail();

            $lot->update([
                'status' => AuctionLot::SOLD,
                'team_player_id' => $teamPlayer->id,
                'sold_at' => now(),
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => null]);

            return $teamPlayer;
        });
    }

    /**
     * Sets the player on the block aside to come back in a later round.
     * Any bids on them are cancelled.
     */
    public function hold(Auction $auction, AuctionLot $lot, int $expectedVersion): AuctionLot
    {
        return $this->setAside($auction, $lot, $expectedVersion, AuctionLot::HOLD);
    }

    /**
     * Puts the player on the block back among the waiting ones (for example
     * the wrong name was called). Any bids on them are cancelled.
     */
    public function release(Auction $auction, AuctionLot $lot, int $expectedVersion): AuctionLot
    {
        return $this->setAside($auction, $lot, $expectedVersion, AuctionLot::PENDING);
    }

    /**
     * Undoes a sale: the player leaves the squad and goes back on the block
     * with the last bid still standing, so it can be corrected. Not possible
     * once they have played a match, or after the auction is completed.
     */
    public function reopenSold(Auction $auction, AuctionLot $lot): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot) {
            $auction = $this->lockAuction($auction);
            $this->assertLive($auction, 'reopen a sale');
            $lot = $this->lockLot($auction, $lot);

            if (! $lot->isSold() || ! $lot->teamPlayer) {
                $this->fail('auction', 'Only a sold player can be reopened.');
            }

            $this->clearBlock($auction);

            if (! $this->teamPlayers->deleteTeamPlayer($lot->teamPlayer)) {
                $this->fail('auction', 'This player has already played a match, so the sale cannot be undone.');
            }

            $lot->update([
                'status' => AuctionLot::LIVE,
                'team_player_id' => null,
                'sold_at' => null,
                'called_at' => now(),
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => $lot->id]);

            return $lot->refresh();
        });
    }

    // ----- Internals --------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function standingFor(Auction $auction, EditionTeam $team): array
    {
        $purse = $auction->purseFor($team);
        $spent = (int) round((float) ($team->spent_points ?? 0));
        $count = (int) $team->team_players_count;
        $left = $purse - $spent;
        $full = $count >= $auction->max_squad;

        // After buying one more player the team still needs this many to
        // reach the minimum squad, and must keep the minimum bid for each.
        $stillNeededAfter = max(0, $auction->min_squad - ($count + 1));
        $reserve = $stillNeededAfter * $auction->min_bid;
        $maxBid = $full ? 0 : max(0, $left - $reserve);

        return [
            'edition_team' => $team,
            'purse' => $purse,
            'spent' => $spent,
            'left' => $left,
            'count' => $count,
            'full' => $full,
            'slots_left' => max(0, $auction->max_squad - $count),
            'still_needed' => max(0, $auction->min_squad - $count),
            'reserve' => $reserve,
            'max_bid' => $maxBid,
            'can_bid' => ! $full && $maxBid >= $auction->min_bid,
        ];
    }

    private function nextBidAmount(Auction $auction, AuctionLot $lot): int
    {
        return $lot->current_bid === null ? $auction->min_bid : $lot->current_bid + $auction->bid_step;
    }

    /**
     * @return Collection<int, int>
     */
    private function eligibleRegistrationIds(int $editionId): Collection
    {
        return PlayerRegistration::query()
            ->where('edition_id', $editionId)
            ->where('payment_status', 'paid')
            ->whereDoesntHave('teamPlayer')
            ->whereHas('player', fn ($query) => $query->where('is_active', true))
            ->pluck('id');
    }

    /**
     * The player on the block goes back to waiting when nobody has bid on
     * them; with a standing bid they must be sold or held first.
     */
    private function clearBlock(Auction $auction): void
    {
        if (! $auction->current_lot_id) {
            return;
        }

        $current = AuctionLot::query()->whereKey($auction->current_lot_id)->lockForUpdate()->first();

        if ($current?->isLive()) {
            if ($current->bids()->standing()->exists()) {
                $this->fail('auction', 'Sell or hold the player on the block first — there is a bid on them.');
            }

            $current->update(['status' => AuctionLot::PENDING, 'version' => $current->version + 1]);
        }

        $auction->update(['current_lot_id' => null]);
    }

    private function setAside(Auction $auction, AuctionLot $lot, int $expectedVersion, string $status): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot, $expectedVersion, $status) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, 'The auction is not live.');
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            $this->cancelStandingBids($lot);

            $lot->update([
                'status' => $status,
                'current_bid' => null,
                'leading_edition_team_id' => null,
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => null]);

            return $lot->refresh();
        });
    }

    private function cancelStandingBids(AuctionLot $lot): void
    {
        $lot->bids()->standing()->update(['cancelled_at' => now()]);
    }

    private function transition(Auction $auction, string $from, string $to, string $message): Auction
    {
        return DB::transaction(function () use ($auction, $from, $to, $message) {
            $auction = $this->lockAuction($auction);

            if ($auction->status !== $from) {
                $this->fail('auction', $message);
            }

            $auction->update(['status' => $to]);

            return $auction;
        });
    }

    /**
     * @return Collection<int, array{edition_team: EditionTeam, count: int, missing: int}>
     */
    private function teamsShortOfMinimum(Auction $auction): Collection
    {
        return $this->teamStandings($auction)
            ->filter(fn (array $row) => $row['still_needed'] > 0)
            ->map(fn (array $row) => [
                'edition_team' => $row['edition_team'],
                'count' => $row['count'],
                'missing' => $row['still_needed'],
            ])
            ->values();
    }

    private function lockAuction(Auction $auction): Auction
    {
        return Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
    }

    private function lockLot(Auction $auction, AuctionLot $lot): AuctionLot
    {
        $locked = AuctionLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

        if ($locked->auction_id !== $auction->id) {
            $this->fail('auction', 'That player is not part of this auction.');
        }

        return $locked;
    }

    /**
     * Calling, bidding, selling and reopening need a live auction; only
     * "next round" and "complete" also work while it is paused.
     */
    private function assertLive(Auction $auction, string $what): void
    {
        if ($auction->isPaused()) {
            $this->fail('auction', 'The auction is paused. Resume it to '.$what.'.');
        }

        if (! $auction->isLive()) {
            $this->fail('auction', 'The auction is not live, so you cannot '.$what.'.');
        }
    }

    /**
     * Next round and complete: any time after the start, while it is live or
     * paused.
     */
    private function assertRunning(Auction $auction, string $message = 'The auction has not been started or is already completed.'): void
    {
        if ($auction->isDraft() || $auction->isCompleted()) {
            $this->fail('auction', $message);
        }
    }

    private function assertOnTheBlock(Auction $auction, AuctionLot $lot, int $expectedVersion): void
    {
        if (! $auction->acceptsBids()) {
            $this->fail('auction', 'The auction is paused. Resume it to carry on.');
        }

        if (! $lot->isLive() || $auction->current_lot_id !== $lot->id) {
            $this->fail('stale', 'That player is no longer on the block. The screen has been refreshed.');
        }

        if ($lot->version !== $expectedVersion) {
            $this->fail('stale', 'This screen is out of date — somebody else just changed this player. It has been refreshed.');
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function onlySettings(array $settings): array
    {
        return array_intersect_key($settings, array_flip([
            'team_purse', 'min_bid', 'bid_step', 'min_squad', 'max_squad', 'show_live_bids',
        ]));
    }

    private function assertSquadLimits(int $min, int $max): void
    {
        if ($min > $max) {
            $this->fail('min_squad', 'The minimum squad cannot be bigger than the maximum.');
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
