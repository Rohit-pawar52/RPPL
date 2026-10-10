<?php

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionEvent;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Database\Eloquent\Builder;
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
            $this->fail('auction', __('This season is completed, so it cannot have a new auction.'));
        }

        if ($edition->auction()->exists()) {
            $this->fail('auction', __('This season already has an auction.'));
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
                $this->fail('auction', __('This auction is completed, so its settings can no longer be changed.'));
            }

            $settings = $this->onlySettings($settings);
            $this->assertSquadLimits((int) ($settings['min_squad'] ?? $auction->min_squad), (int) ($settings['max_squad'] ?? $auction->max_squad));

            $auction->update($settings);
            $changedSettings = array_values(array_diff(array_keys($auction->getChanges()), ['updated_at']));

            $teams = EditionTeam::query()->where('edition_id', $auction->edition_id)->whereIn('id', array_keys($teamPurses))->get();

            foreach ($teams as $team) {
                $team->update(['auction_purse' => filled($teamPurses[$team->id] ?? null) ? (int) $teamPurses[$team->id] : null]);

                if ($team->wasChanged('auction_purse')) {
                    $this->logEvent($auction, AuctionEvent::SETTINGS, null, $team, $team->auction_purse, 'team_purse');
                }
            }

            if ($changedSettings !== []) {
                $this->logEvent($auction, AuctionEvent::SETTINGS, null, null, null, implode(', ', $changedSettings));
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
                $this->fail('auction', __('This auction is completed, so its player pool can no longer change.'));
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

            if ($toAdd->count() > 0 || $removed > 0) {
                $this->logEvent($auction, AuctionEvent::POOL, null, null, null, '+'.$toAdd->count().' / -'.$removed);
            }

            return ['added' => $toAdd->count(), 'removed' => $removed];
        });
    }

    /**
     * Keeps the pool right for ONE registration the moment something about
     * it changes (it is paid, refunded, put in or taken out of a squad...):
     * a paid, active player who is in no squad and not yet in the auction
     * joins the waiting players; a waiting (pending / hold) player who is no
     * longer eligible leaves. A player who is sold but no longer in any squad
     * (taken out on the squad page) has the sale undone and waits again. Players
     * who are live or unsold are never touched, and a completed auction is left
     * alone. Called by the model
     * hooks in AppServiceProvider, so nobody has to remember to "update the
     * pool" — that button stays as the manual safety net.
     */
    public function syncRegistration(PlayerRegistration $registration): void
    {
        $auction = Auction::query()
            ->where('edition_id', $registration->edition_id)
            ->whereIn('status', [Auction::STATUS_DRAFT, Auction::STATUS_LIVE, Auction::STATUS_PAUSED])
            ->first();

        if (! $auction) {
            return;
        }

        $eligible = $this->eligibleRegistrations($registration->edition_id)->whereKey($registration->id)->exists();
        $lot = $auction->lots()->where('player_registration_id', $registration->id)->first();

        if ($eligible && ! $lot) {
            AuctionLot::create([
                'auction_id' => $auction->id,
                'player_registration_id' => $registration->id,
                'status' => AuctionLot::PENDING,
                'round' => $auction->round,
            ]);
        } elseif (! $eligible && $lot && in_array($lot->status, [AuctionLot::PENDING, AuctionLot::HOLD], true)) {
            $lot->delete();
        } elseif ($eligible && $lot && $lot->isSold()) {
            $this->resetSoldLot($auction, $lot);
        }
    }

    /**
     * What "Update the pool" would change, without changing it: paid players
     * who are not in the pool, and waiting players who should not be. The
     * console shows this as a notice.
     *
     * @return array{missing: list<string>, stale: list<string>}
     */
    public function poolCheck(Auction $auction): array
    {
        $eligible = $this->eligibleRegistrationIds($auction->edition_id);
        $lots = $auction->lots()->get(['player_registration_id', 'status']);

        $missing = $eligible->diff($lots->pluck('player_registration_id'));
        $stale = $lots
            ->filter(fn (AuctionLot $lot) => in_array($lot->status, [AuctionLot::PENDING, AuctionLot::HOLD], true))
            ->pluck('player_registration_id')
            ->diff($eligible);

        $names = fn ($ids) => $ids->isEmpty()
            ? []
            : PlayerRegistration::query()
                ->whereIn('id', $ids->all())
                ->with('player')
                ->get()
                ->map(fn (PlayerRegistration $registration) => $registration->player->name)
                ->sort()
                ->values()
                ->all();

        return ['missing' => $names($missing), 'stale' => $names($stale)];
    }

    /**
     * Someone who turns up on the day without having registered online: the
     * player (found by mobile number, never edited) and a paid registration
     * for this season are created, and they join the waiting players — to be
     * called like anyone else. The same door as "registered offline" on the
     * Squads page, minus putting them straight in a team.
     */
    public function addWalkInPlayer(Auction $auction, string $name, string $phone, ?string $village = null, ?string $role = null): AuctionLot
    {
        return DB::transaction(function () use ($auction, $name, $phone, $village, $role) {
            $auction = $this->lockAuction($auction);

            if ($auction->isCompleted()) {
                $this->fail('auction', __('This auction is completed, so no more players can be added.'));
            }

            $edition = Edition::findOrFail($auction->edition_id);
            $normalised = Player::normalizePhone($phone);

            if ($normalised === null || strlen(preg_replace('/\D/', '', $normalised)) < 7) {
                $this->fail('phone', __('Enter the player\'s mobile number.'));
            }

            $player = Player::where('phone', $normalised)->first();

            if ($player && ! $player->is_active) {
                $this->fail('phone', __('This player is inactive and cannot be added.'));
            }

            if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
                $this->fail('phone', __(':name is already registered for this season. Once their payment is marked paid they join the pool by themselves.', ['name' => $player->name]));
            }

            $player ??= Player::create([
                'name' => $name,
                'phone' => $normalised,
                'primary_role' => array_key_exists((string) $role, Player::PRIMARY_ROLE_LABELS) ? $role : null,
            ]);

            $registration = $this->registrations->createRegistration([
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => 'paid',
                'registration_fee' => $edition->registration_fee,
                'registered_at' => now(),
                'village' => filled($village) ? trim($village) : null,
            ]);

            // Saving the paid registration already put the player in the pool
            // (syncRegistration); this only makes sure of it.
            $lot = AuctionLot::firstOrCreate(
                ['auction_id' => $auction->id, 'player_registration_id' => $registration->id],
                ['status' => AuctionLot::PENDING, 'round' => $auction->round],
            );

            $this->logEvent($auction, AuctionEvent::WALK_IN, $lot);

            return $lot;
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
                $this->fail('auction', __('This auction has already been started.'));
            }

            if (! EditionTeam::where('edition_id', $auction->edition_id)->exists()) {
                $this->fail('auction', __('Add the season\'s teams before starting the auction.'));
            }

            if (! $auction->lots()->exists()) {
                $this->fail('auction', __('There are no paid players in the pool yet.'));
            }

            $auction->update(['status' => Auction::STATUS_LIVE, 'started_at' => now()]);
            $this->logEvent($auction, AuctionEvent::STARTED);

            return $auction;
        });
    }

    public function pause(Auction $auction): Auction
    {
        return $this->transition($auction, Auction::STATUS_LIVE, Auction::STATUS_PAUSED, __('Only a live auction can be paused.'));
    }

    public function resume(Auction $auction): Auction
    {
        return $this->transition($auction, Auction::STATUS_PAUSED, Auction::STATUS_LIVE, __('Only a paused auction can be resumed.'));
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

            $back = $auction->lots()
                ->where('status', AuctionLot::HOLD)
                ->update(['status' => AuctionLot::PENDING, 'round' => $round, 'version' => DB::raw('version + 1')]);

            $this->logEvent($auction, AuctionEvent::NEXT_ROUND, null, null, $back, (string) $round);

            return $back;
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
            $this->logEvent($auction, AuctionEvent::COMPLETED, null, null, $unsold);

            return ['unsold' => $unsold, 'short_teams' => $this->teamsShortOfMinimum($auction)];
        });
    }

    /**
     * Opens a completed auction again, for the day someone pressed Complete too early (or a late player turns up).
     *
     * Everything that was decided stays: sold players keep their team and price, nothing is deleted. Completing
     * turned everyone still waiting into "unsold" (nothing else ever does), so those players go back to waiting.
     * The auction comes back PAUSED, so nothing can happen by a stray tap until the auctioneer resumes it. The
     * pool is then brought in line with the registrations and the squads, so a player who was put in a team by
     * hand in the meantime is not offered again. Refused unless the auction is completed and its season is not.
     *
     * @return array{returned: int}
     */
    public function reopen(Auction $auction): array
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);

            if (! $auction->isCompleted()) {
                $this->fail('auction', __('Only a completed auction can be reopened.'));
            }

            if (Edition::query()->whereKey($auction->edition_id)->value('status') === 'completed') {
                $this->fail('auction', __('This season is completed, so its auction cannot be reopened.'));
            }

            $returned = $auction->lots()
                ->where('status', AuctionLot::UNSOLD)
                ->update([
                    'status' => AuctionLot::PENDING,
                    'current_bid' => null,
                    'leading_edition_team_id' => null,
                    'team_player_id' => null,
                    'called_at' => null,
                    'sold_at' => null,
                    'round' => $auction->round,
                    'version' => DB::raw('version + 1'),
                ]);

            $auction->update(['status' => Auction::STATUS_PAUSED, 'completed_at' => null, 'current_lot_id' => null]);

            // Players put in a team by hand since, or whose payment changed, leave / join the waiting players.
            $this->refreshPool($auction);

            $this->logEvent($auction, AuctionEvent::REOPENED, null, null, $returned);

            return ['returned' => $returned];
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
            $this->assertLive($auction, __('The auction is paused. Resume it to call a player.'), __('The auction is not live, so you cannot call a player.'));
            $lot = $this->lockLot($auction, $lot);

            if ($lot->isLive() && $auction->current_lot_id === $lot->id) {
                return $lot;
            }

            if (! in_array($lot->status, [AuctionLot::PENDING, AuctionLot::HOLD], true)) {
                $this->fail('auction', __('Only a waiting or hold player can be called.'));
            }

            $this->clearBlock($auction);

            $lot->update([
                'status' => AuctionLot::LIVE,
                'round' => $auction->round,
                'called_at' => now(),
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => $lot->id]);
            $this->logEvent($auction, AuctionEvent::CALLED, $lot);

            return $lot->refresh();
        });
    }

    /**
     * A random player from the pending ones, or null when nobody is left
     * waiting (the auctioneer can then start the next round).
     */
    public function callRandom(Auction $auction, ?string $role = null): ?AuctionLot
    {
        $pick = $auction->lots()
            ->where('status', AuctionLot::PENDING)
            ->when($role !== null && $role !== '', fn (Builder $query) => $query->whereHas('playerRegistration.player', fn (Builder $players) => $players->where('primary_role', $role)))
            ->inRandomOrder()
            ->first();

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

            $this->assertRunning($auction, __('The auction is not live.'));
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            if ($team->edition_id !== $auction->edition_id) {
                $this->fail('bid', __('That team is not in this season.'));
            }

            $team->loadMissing('team');
            $name = $team->team->name;

            if ($lot->leading_edition_team_id === $team->id) {
                $this->fail('bid', __(':name is already leading — another team has to bid.', ['name' => $name]));
            }

            $standing = $this->teamStanding($auction, $team);

            if ($standing['full']) {
                $this->fail('bid', __(':name already has a full squad of :max.', ['name' => $name, 'max' => $auction->max_squad]));
            }

            $next = $this->nextBidAmount($auction, $lot);
            $amount ??= $next;

            if ($amount < $next) {
                $this->fail('bid', __('The bid must be at least :amount pts.', ['amount' => points($next)]));
            }

            if (($amount - $auction->min_bid) % $auction->bid_step !== 0) {
                $this->fail('bid', __('A bid goes up in steps of :step (from :min).', ['step' => points($auction->bid_step), 'min' => points($auction->min_bid)]));
            }

            if ($amount > $standing['left']) {
                $this->fail('bid', __(':name has only :amount pts left.', ['name' => $name, 'amount' => points($standing['left'])]));
            }

            $overLimit = $amount > $standing['max_bid'];

            if ($overLimit && ! $override) {
                $this->fail('reserve', __(':name must keep :reserve pts to still reach a squad of :min — the most it can bid now is :max pts.', ['name' => $name, 'reserve' => points($standing['reserve']), 'min' => $auction->min_squad, 'max' => points($standing['max_bid'])]));
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
            $this->logEvent($auction, AuctionEvent::BID, $lot, $team, $amount, $overLimit ? 'override' : null);

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
            $this->assertRunning($auction, __('The auction is not live.'));
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            $latest = $lot->bids()->standing()->latest('id')->first();

            if (! $latest) {
                $this->fail('bid', __('There is no bid to undo.'));
            }

            $latest->update(['cancelled_at' => now()]);

            $previous = $lot->bids()->standing()->latest('id')->first();

            $lot->update([
                'current_bid' => $previous?->amount,
                'leading_edition_team_id' => $previous?->edition_team_id,
                'version' => $lot->version + 1,
            ]);
            $this->logEvent($auction, AuctionEvent::BID_UNDONE, $lot, $latest->editionTeam, (int) $latest->amount);

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
            $this->assertRunning($auction, __('The auction is not live.'));
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            if ($lot->current_bid === null || $lot->leading_edition_team_id === null) {
                $this->fail('sell', __('There is no bid yet, so the player cannot be sold.'));
            }

            $team = EditionTeam::findOrFail($lot->leading_edition_team_id);
            $standing = $this->teamStanding($auction, $team);

            // Re-checked at the moment of sale: the squad page may have been
            // used since the bid was placed.
            if ($standing['full']) {
                $this->fail('sell', __(':name already has a full squad of :max.', ['name' => $team->team->name, 'max' => $auction->max_squad]));
            }

            if ($lot->current_bid > $standing['left']) {
                $this->fail('sell', __(':name has only :amount pts left, less than the bid.', ['name' => $team->team->name, 'amount' => points($standing['left'])]));
            }

            $registration = PlayerRegistration::findOrFail($lot->player_registration_id);

            if ($this->teamPlayers->addPlayers($team, [$registration->id => $lot->current_bid]) !== 1) {
                $this->fail('sell', __('This player is already in a squad or is inactive, so cannot be sold.'));
            }

            $teamPlayer = TeamPlayer::query()->where('player_registration_id', $registration->id)->firstOrFail();

            $lot->update([
                'status' => AuctionLot::SOLD,
                'team_player_id' => $teamPlayer->id,
                'sold_at' => now(),
                'version' => $lot->version + 1,
            ]);
            $auction->update(['current_lot_id' => null]);
            $this->logEvent($auction, AuctionEvent::SOLD, $lot, $team, (int) $lot->current_bid);

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
            $this->assertLive($auction, __('The auction is paused. Resume it to reopen a sale.'), __('The auction is not live, so you cannot reopen a sale.'));
            $lot = $this->lockLot($auction, $lot);

            if (! $lot->isSold() || ! $lot->teamPlayer) {
                $this->fail('auction', __('Only a sold player can be reopened.'));
            }

            $this->clearBlock($auction);

            $teamPlayer = $lot->teamPlayer;

            // The player is on the block again before the squad row goes, so
            // the pool sync that watches the squads sees someone being called,
            // not a sale to undo. A refusal below rolls all of this back.
            $lot->update([
                'status' => AuctionLot::LIVE,
                'team_player_id' => null,
                'sold_at' => null,
                'called_at' => now(),
                'version' => $lot->version + 1,
            ]);

            if (! $this->teamPlayers->deleteTeamPlayer($teamPlayer)) {
                $this->fail('auction', __('This player has already played a match, so the sale cannot be undone.'));
            }

            $auction->update(['current_lot_id' => $lot->id]);
            $this->logEvent($auction, AuctionEvent::SALE_REOPENED, $lot, null, $lot->current_bid);

            return $lot->refresh();
        });
    }

    /**
     * Takes a sold player back from their team without touching the player on
     * the block: the points return to the team's purse, the bids on the sale
     * are dropped and the player waits with the others, to be called again
     * later. Works for any sale, new or old, while the auction is live or
     * paused. Not possible once the player has played a match.
     */
    public function returnToWaiting(Auction $auction, AuctionLot $lot): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction);
            $lot = $this->lockLot($auction, $lot);

            if (! $lot->isSold()) {
                $this->fail('auction', __('Only a sold player can be taken back.'));
            }

            $teamPlayer = $lot->teamPlayer;

            // Waiting again before the squad row goes, for the same reason as
            // in reopenSold(). A sale whose squad row is already gone (taken
            // out on the squad page) simply has nothing to delete.
            $this->resetSoldLot($auction, $lot);

            if ($teamPlayer && ! $this->teamPlayers->deleteTeamPlayer($teamPlayer)) {
                $this->fail('auction', __('This player has already played a match, so the sale cannot be undone.'));
            }

            $this->logEvent($auction, AuctionEvent::TAKEN_BACK, $lot);

            return $lot->refresh();
        });
    }

    /**
     * A sold lot goes back to waiting: its bids are cancelled and nothing of
     * the sale is kept.
     */
    private function resetSoldLot(Auction $auction, AuctionLot $lot): void
    {
        $this->cancelStandingBids($lot);

        $lot->update([
            'status' => AuctionLot::PENDING,
            'current_bid' => null,
            'leading_edition_team_id' => null,
            'team_player_id' => null,
            'sold_at' => null,
            'round' => $auction->round,
            'version' => $lot->version + 1,
        ]);
    }

    // ----- Corrections and a fresh start -----------------------------------

    /**
     * Fixes a player's details from the console while the auction runs: the name and role (the player), the
     * village (this season's registration). Nothing else is touched; the public screens catch up at once.
     *
     * @param  array{name?: ?string, village?: ?string, primary_role?: ?string}  $data
     */
    public function updatePlayerDetails(Auction $auction, AuctionLot $lot, array $data): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot, $data) {
            $auction = $this->lockAuction($auction);
            $lot = $this->lockLot($auction, $lot);
            $lot->loadMissing('playerRegistration.player');
            $registration = $lot->playerRegistration;
            $player = $registration->player;

            $name = isset($data['name']) ? trim((string) $data['name']) : $player->name;
            $role = $data['primary_role'] ?? null;
            $village = array_key_exists('village', $data) ? (filled($data['village']) ? trim((string) $data['village']) : null) : $registration->village;

            if ($name === '') {
                $this->fail('player', __('The name cannot be empty.'));
            }

            if ($role !== null && $role !== '' && ! array_key_exists($role, Player::PRIMARY_ROLE_LABELS)) {
                $this->fail('player', __('Choose a role from the list.'));
            }

            $changed = [];

            if ($name !== $player->name) {
                $player->update(['name' => $name]);
                $changed[] = 'name';
            }

            if (($role ?? '') !== '' && $role !== $player->primary_role) {
                $player->update(['primary_role' => $role]);
                $changed[] = 'role';
            }

            if ($village !== $registration->village) {
                $registration->update(['village' => $village]);
                $changed[] = 'village';
            }

            // The public screens listen to the lot: touching it makes them redraw with the new details.
            $lot->touch();

            if ($changed !== []) {
                $this->logEvent($auction, AuctionEvent::PLAYER_EDITED, $lot, null, null, implode(', ', $changed));
            }

            return $lot->refresh();
        });
    }

    /**
     * The latest bid went to the wrong team: it moves, at the same amount, to the right one. Refused (and nothing
     * changes) if that team cannot afford it or is already leading.
     */
    public function moveLatestBid(Auction $auction, AuctionLot $lot, EditionTeam $team, int $expectedVersion, ?User $by = null): AuctionBid
    {
        return DB::transaction(function () use ($auction, $lot, $team, $expectedVersion, $by) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, __('The auction is not live.'));
            $lot = $this->lockLot($auction, $lot);
            $this->assertOnTheBlock($auction, $lot, $expectedVersion);

            $latest = $lot->bids()->standing()->latest('id')->first();

            if (! $latest) {
                $this->fail('bid', __('There is no bid to move.'));
            }

            if ($latest->edition_team_id === $team->id) {
                $this->fail('bid', __('That team already has this bid.'));
            }

            $from = $latest->editionTeam;
            $amount = (int) $latest->amount;
            $override = (bool) $latest->is_override;

            $latest->update(['cancelled_at' => now()]);
            $previous = $lot->bids()->standing()->latest('id')->first();

            $lot->update([
                'current_bid' => $previous?->amount,
                'leading_edition_team_id' => $previous?->edition_team_id,
                'version' => $lot->version + 1,
            ]);

            $bid = $this->placeBid($auction, $lot->fresh(), $team, $lot->fresh()->version, $amount, null, $override, $by);
            $this->logEvent($auction, AuctionEvent::BID_TEAM_CHANGED, $lot->fresh(), $team, $amount, $from?->team?->name);

            return $bid;
        });
    }

    /**
     * Corrects what a sold player went for. Same rules as a bid (the step from the minimum bid) and the team must
     * be able to afford the difference. The squad row is the source of truth for the price.
     */
    public function changeSoldPrice(Auction $auction, AuctionLot $lot, int $amount): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot, $amount) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, __('The auction is not live.'));
            $lot = $this->lockLot($auction, $lot);
            $lot->loadMissing('teamPlayer.editionTeam.team');

            if (! $lot->isSold() || ! $lot->teamPlayer) {
                $this->fail('price', __('Only a sold player has a price to correct.'));
            }

            if ($amount < $auction->min_bid || ($amount - $auction->min_bid) % $auction->bid_step !== 0) {
                $this->fail('price', __('A price goes up in steps of :step (from :min).', ['step' => points($auction->bid_step), 'min' => points($auction->min_bid)]));
            }

            $teamPlayer = $lot->teamPlayer;
            $team = $teamPlayer->editionTeam;
            $old = (int) round((float) $teamPlayer->sold_amount);
            $standing = $this->teamStanding($auction, $team);

            if ($amount - $old > $standing['left']) {
                $this->fail('price', __(':name has only :amount pts left, less than the new price.', ['name' => $team->team->name, 'amount' => points($standing['left'])]));
            }

            if ($amount === $old) {
                return $lot;
            }

            $teamPlayer->update(['sold_amount' => $amount]);
            $lot->update(['current_bid' => $amount, 'version' => $lot->version + 1]);
            $this->logEvent($auction, AuctionEvent::PRICE_CHANGED, $lot, $team, $amount, (string) $old);

            return $lot->refresh();
        });
    }

    /**
     * Back to a fresh start - for a rehearsal on the real data: every sold player leaves their team, every bid is
     * dropped, every player waits again and the auction returns to its set-up state (round 1, not started).
     * Refused for a completed auction, and if a sold player has already played a match.
     */
    public function resetAll(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction) {
            $auction = $this->lockAuction($auction);

            if ($auction->isCompleted()) {
                $this->fail('auction', __('A completed auction cannot be reset.'));
            }

            $lots = $auction->lots()->with('teamPlayer')->lockForUpdate()->get();

            foreach ($lots as $lot) {
                if ($lot->teamPlayer && $lot->teamPlayer->matchPlayers()->exists()) {
                    $this->fail('auction', __(':name has already played a match, so the auction cannot be reset.', ['name' => $lot->playerRegistration->player->name]));
                }
            }

            foreach ($lots->filter(fn (AuctionLot $lot) => $lot->isSold()) as $lot) {
                $teamPlayer = $lot->teamPlayer;
                $this->resetSoldLot($auction, $lot);

                if ($teamPlayer) {
                    $this->teamPlayers->deleteTeamPlayer($teamPlayer);
                }
            }

            $auction->lots()->where('status', '!=', AuctionLot::PENDING)->update([
                'status' => AuctionLot::PENDING,
                'current_bid' => null,
                'leading_edition_team_id' => null,
                'team_player_id' => null,
                'called_at' => null,
                'sold_at' => null,
                'version' => DB::raw('version + 1'),
            ]);

            AuctionBid::query()->whereIn('auction_lot_id', $auction->lots()->select('id'))->delete();

            $auction->update(['status' => Auction::STATUS_DRAFT, 'round' => 1, 'current_lot_id' => null, 'started_at' => null]);
            $auction->lots()->update(['round' => 1]);

            $this->logEvent($auction, AuctionEvent::RESET);

            return $auction->refresh();
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
        return $this->eligibleRegistrations($editionId)->pluck('id');
    }

    /**
     * Who may be in the pool: paid, active, and in no squad yet.
     */
    private function eligibleRegistrations(int $editionId): Builder
    {
        return PlayerRegistration::query()
            ->where('edition_id', $editionId)
            ->where('payment_status', 'paid')
            ->whereDoesntHave('teamPlayer')
            ->whereHas('player', fn ($query) => $query->where('is_active', true));
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
                $this->fail('auction', __('Sell or hold the player on the block first — there is a bid on them.'));
            }

            $current->update(['status' => AuctionLot::PENDING, 'version' => $current->version + 1]);
        }

        $auction->update(['current_lot_id' => null]);
    }

    private function setAside(Auction $auction, AuctionLot $lot, int $expectedVersion, string $status): AuctionLot
    {
        return DB::transaction(function () use ($auction, $lot, $expectedVersion, $status) {
            $auction = $this->lockAuction($auction);
            $this->assertRunning($auction, __('The auction is not live.'));
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
            $this->logEvent($auction, $status === AuctionLot::HOLD ? AuctionEvent::HELD : AuctionEvent::RELEASED, $lot);

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
            $this->logEvent($auction, $to === Auction::STATUS_PAUSED ? AuctionEvent::PAUSED : AuctionEvent::RESUMED);

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

    /**
     * One line of the activity log: who (the signed-in user, if any), what, and the player / team / amount it was
     * about. Written inside the transaction of the change itself.
     */
    private function logEvent(Auction $auction, string $type, ?AuctionLot $lot = null, ?EditionTeam $team = null, ?int $amount = null, ?string $note = null): void
    {
        $playerName = null;

        if ($lot) {
            $lot->loadMissing('playerRegistration.player');
            $playerName = $lot->playerRegistration?->player?->name;
        }

        $teamName = null;

        if ($team) {
            $team->loadMissing('team');
            $teamName = $team->team?->name;
        }

        AuctionEvent::query()->create([
            'auction_id' => $auction->id,
            'auction_lot_id' => $lot?->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'player_name' => $playerName,
            'team_name' => $teamName,
            'amount' => $amount,
            'note' => $note !== null ? mb_substr($note, 0, 250) : null,
        ]);
    }

    private function lockAuction(Auction $auction): Auction
    {
        return Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
    }

    private function lockLot(Auction $auction, AuctionLot $lot): AuctionLot
    {
        $locked = AuctionLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

        if ($locked->auction_id !== $auction->id) {
            $this->fail('auction', __('That player is not part of this auction.'));
        }

        return $locked;
    }

    /**
     * Calling, bidding, selling and reopening need a live auction; only
     * "next round" and "complete" also work while it is paused.
     */
    private function assertLive(Auction $auction, string $pausedMessage, string $notLiveMessage): void
    {
        if ($auction->isPaused()) {
            $this->fail('auction', $pausedMessage);
        }

        if (! $auction->isLive()) {
            $this->fail('auction', $notLiveMessage);
        }
    }

    /**
     * Next round and complete: any time after the start, while it is live or
     * paused.
     */
    private function assertRunning(Auction $auction, ?string $message = null): void
    {
        if ($auction->isDraft() || $auction->isCompleted()) {
            $this->fail('auction', $message ?? __('The auction has not been started or is already completed.'));
        }
    }

    private function assertOnTheBlock(Auction $auction, AuctionLot $lot, int $expectedVersion): void
    {
        if (! $auction->acceptsBids()) {
            $this->fail('auction', __('The auction is paused. Resume it to carry on.'));
        }

        if (! $lot->isLive() || $auction->current_lot_id !== $lot->id) {
            $this->fail('stale', __('That player is no longer on the block. The screen has been refreshed.'));
        }

        if ($lot->version !== $expectedVersion) {
            $this->fail('stale', __('This screen is out of date — somebody else just changed this player. It has been refreshed.'));
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function onlySettings(array $settings): array
    {
        return array_intersect_key($settings, array_flip([
            'team_purse', 'min_bid', 'bid_step', 'min_squad', 'max_squad', 'show_live_bids', 'notify_start', 'notify_sale_min',
        ]));
    }

    private function assertSquadLimits(int $min, int $max): void
    {
        if ($min > $max) {
            $this->fail('min_squad', __('The minimum squad cannot be bigger than the maximum.'));
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
