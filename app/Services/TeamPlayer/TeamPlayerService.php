<?php

namespace App\Services\TeamPlayer;

use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamPlayerService
{
    public function __construct(private readonly PlayerRegistrationService $registrations) {}

    /**
     * StoreTeamPlayerRequest already validates the same-edition
     * consistency rule (the most important rule of this phase), active
     * team/player, non-completed edition, and the duplicate-
     * registration/jersey checks. This defensively re-verifies the
     * same-edition rule directly against fresh data before writing,
     * since it is the one rule this project was explicitly asked to
     * stop deferring — cheap insurance against it ever being bypassed
     * by a future caller of this service that skips the FormRequest.
     */
    public function createTeamPlayer(array $data): TeamPlayer
    {
        $editionTeam = EditionTeam::findOrFail($data['edition_team_id']);
        $registration = PlayerRegistration::findOrFail($data['player_registration_id']);

        if ($registration->edition_id !== $editionTeam->edition_id) {
            throw ValidationException::withMessages([
                'player_registration_id' => __('The selected player is not registered for the same edition as the selected team.'),
            ]);
        }

        try {
            return TeamPlayer::create($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'player_registration_id' => __('This player is already assigned to a squad, or the jersey number is already taken on this team.'),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Adds several registrations of the team's season to its squad at once
     * (the auction result), each with an optional sold amount. A
     * registration that does not belong to the season, whose player is
     * inactive, or that is already in a squad is skipped, so a stale form
     * never fails the whole batch. Returns how many were added.
     *
     * @param  array<int, ?string>  $amounts  registration id => sold amount (null/blank = none)
     */
    public function addPlayers(EditionTeam $editionTeam, array $amounts): int
    {
        return DB::transaction(function () use ($editionTeam, $amounts) {
            $registrations = PlayerRegistration::query()
                ->where('edition_id', $editionTeam->edition_id)
                ->whereIn('id', array_keys($amounts))
                ->whereDoesntHave('teamPlayer')
                ->whereHas('player', fn ($query) => $query->where('is_active', true))
                ->get();

            foreach ($registrations as $registration) {
                TeamPlayer::create([
                    'edition_team_id' => $editionTeam->id,
                    'player_registration_id' => $registration->id,
                    'sold_amount' => filled($amounts[$registration->id] ?? null) ? $amounts[$registration->id] : null,
                ]);
            }

            return $registrations->count();
        });
    }

    /**
     * Saves the jersey number, role and sold amount of a team's squad rows
     * in one go. Jersey numbers are unique within the team, and a swap
     * (5 <-> 6) would collide halfway through, so the numbers that change
     * are cleared first and then set.
     *
     * @param  array<int, array{jersey_number: ?int, role: ?string, sold_amount: ?string}>  $rows  team player id => values
     */
    public function updateSquad(EditionTeam $editionTeam, array $rows): int
    {
        return DB::transaction(function () use ($editionTeam, $rows) {
            $players = $editionTeam->teamPlayers()->whereIn('id', array_keys($rows))->get();

            $changing = $players->filter(fn (TeamPlayer $player) => $player->jersey_number !== ($rows[$player->id]['jersey_number'] ?? null));

            if ($changing->isNotEmpty()) {
                TeamPlayer::whereIn('id', $changing->modelKeys())->update(['jersey_number' => null]);
            }

            foreach ($players as $player) {
                $row = $rows[$player->id];

                $player->update([
                    'jersey_number' => $row['jersey_number'] ?? null,
                    'role' => $row['role'] ?? null,
                    'sold_amount' => filled($row['sold_amount'] ?? null) ? $row['sold_amount'] : null,
                ]);
            }

            return $players->count();
        });
    }

    /**
     * An offline registration bought in the auction: creates the player
     * (or finds the existing one by mobile number — never edited), their
     * registration for this season (paid by default: they paid offline)
     * and the squad entry, in one step.
     *
     * @param  array{name: string, phone: string, sold_amount?: ?string, payment_status?: string}  $data
     *
     * @throws ValidationException when the player is inactive or already registered this season
     */
    public function createOfflinePlayer(EditionTeam $editionTeam, array $data): TeamPlayer
    {
        $editionTeam->loadMissing('edition');
        $edition = $editionTeam->edition;
        $phone = Player::normalizePhone($data['phone']);

        return DB::transaction(function () use ($editionTeam, $edition, $data, $phone) {
            $player = Player::where('phone', $phone)->first();

            if ($player && ! $player->is_active) {
                throw ValidationException::withMessages(['phone' => __('This player is inactive and cannot be added.')]);
            }

            if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => __(':name is already registered for this season — tick them in the list above instead.', ['name' => $player->name]),
                ]);
            }

            $player ??= Player::create(['name' => $data['name'], 'phone' => $phone]);

            $registration = $this->registrations->createRegistration([
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => $data['payment_status'] ?? 'paid',
                'registration_fee' => $edition->registration_fee,
                'registered_at' => now(),
            ]);

            return TeamPlayer::create([
                'edition_team_id' => $editionTeam->id,
                'player_registration_id' => $registration->id,
                'sold_amount' => filled($data['sold_amount'] ?? null) ? $data['sold_amount'] : null,
            ]);
        });
    }

    /**
     * edition_team_id/player_registration_id are never part of $data
     * here — see UpdateTeamPlayerRequest, which only validates squad
     * metadata (jersey_number, role). A squad assignment's identity is
     * immutable after creation.
     */
    public function updateTeamPlayer(TeamPlayer $teamPlayer, array $data): TeamPlayer
    {
        $teamPlayer->update($data);

        return $teamPlayer;
    }

    /**
     * Deletes a squad assignment only when it has never been used in a
     * match. Returns false instead of letting the FK constraint fail,
     * so the controller can show a friendly message.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): MatchPlayerController::store() is a
     * live admin write path, and match_players.team_player_id
     * cascade-deletes on TeamPlayer deletion, so a concurrent Playing XI
     * selection between an unlocked check and the delete would be
     * silently destroyed.
     */
    public function deleteTeamPlayer(TeamPlayer $teamPlayer): bool
    {
        return DB::transaction(function () use ($teamPlayer) {
            $locked = TeamPlayer::whereKey($teamPlayer->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasMatchHistory($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    public function hasMatchHistory(TeamPlayer $teamPlayer): bool
    {
        return $teamPlayer->matchPlayers()->exists();
    }
}
