<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Empties the tournament data (the demo / test dataset a fresh site starts with) so the site can be used for
 * real, while the seeders stay in the code: `php artisan db:seed` brings the demo data back whenever it is
 * wanted for testing (they are safe to re-run).
 *
 * What is removed: seasons and everything inside them (teams, players, registrations, squads, matches and
 * the ball-by-ball scoring, auctions, finance, contributors, committee), news, photos, videos, rules,
 * announcements, notifications and the venues.
 *
 * What is kept: the login accounts, roles and permissions, every setting (branding, UPI, theme ...), the
 * three content pages, sponsor ads, push subscriptions, page-view statistics, the cleanup log and all of
 * Laravel's own tables (migrations, cache, jobs, sessions). Uploaded files are not touched either (the
 * Media files tab in Data Cleanup removes the pictures nothing refers to any more).
 *
 * Without --force it only shows what would go. --token makes a run happen ONCE per token: docker/start.sh
 * passes the CLEAR_DEMO_DATA variable as the token, so leaving that variable set on Render can never wipe
 * the site again on a later restart; choose a different value to run it again.
 */
class ClearDemoDataCommand extends Command
{
    protected $signature = 'rppl:clear-demo-data
                            {--force : Really delete (without it only the numbers are shown)}
                            {--token= : A run with a token that was already used is skipped}';

    protected $description = 'Remove the demo / test tournament data, keeping accounts, settings, sponsors and pages';

    /** Tables emptied, in no particular order (foreign keys are handled by retrying). */
    public const WIPE = [
        'announcements', 'auction_bids', 'auction_lots', 'auctions', 'committee_members', 'contributors',
        'deliveries', 'delivery_corrections', 'edition_committee_members', 'edition_contributions',
        'edition_teams', 'edition_transactions', 'editions', 'innings', 'match_players', 'matches', 'news',
        'news_images', 'notification_sends', 'notifications', 'photos', 'player_registrations', 'players',
        'rule_types', 'rules', 'scoring_events', 'team_players', 'teams', 'tournament_day_notifications',
        'venues', 'videos',
    ];

    private const TOKEN_GROUP = 'maintenance';

    private const TOKEN_KEY = 'clear_demo_data_token';

    public function handle(): int
    {
        $token = trim((string) $this->option('token'));

        if ($token !== '' && Setting::query()->where('group', self::TOKEN_GROUP)->where('key', self::TOKEN_KEY)->value('value') === $token) {
            $this->info("Skipped: the demo data was already cleared with the token \"{$token}\" (use another value to run it again).");

            return self::SUCCESS;
        }

        $tables = array_values(array_filter(self::WIPE, fn (string $table) => Schema::hasTable($table)));
        $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->table(['Table', 'Rows to remove'], $counts->filter()->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Total rows: '.$counts->sum());

        if (! $this->option('force')) {
            $this->warn('Nothing was deleted. Run again with --force to remove them.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tables, $token) {
            $this->deleteAll($tables);

            if ($token !== '') {
                Setting::query()->updateOrCreate(
                    ['group' => self::TOKEN_GROUP, 'key' => self::TOKEN_KEY],
                    ['value' => $token, 'type' => Setting::TYPE_STRING],
                );
            }
        });

        $this->restartNumbering($tables);

        $this->info('Demo data cleared ('.$counts->sum().' rows). Accounts, settings, sponsors and pages were kept.');

        return self::SUCCESS;
    }

    /**
     * Delete every table's rows. A table another one still points at refuses until that one is empty, so the
     * tables are retried in rounds until all are done (each try is its own savepoint, so a refusal does not
     * spoil the transaction).
     *
     * @param  list<string>  $tables
     */
    private function deleteAll(array $tables): void
    {
        $pending = $tables;

        for ($round = 0; $round <= count($tables) && $pending !== []; $round++) {
            foreach ($pending as $index => $table) {
                try {
                    DB::transaction(fn () => DB::table($table)->delete());
                    unset($pending[$index]);
                } catch (QueryException) {
                    // Still referenced by a table that is not empty yet: try again in the next round.
                }
            }
        }

        if ($pending !== []) {
            throw new RuntimeException('Could not empty: '.implode(', ', $pending).'. Nothing was deleted.');
        }
    }

    /**
     * Start the id numbers from 1 again, so the first new season / player / match is number 1.
     *
     * @param  list<string>  $tables
     */
    private function restartNumbering(array $tables): void
    {
        $driver = DB::connection()->getDriverName();

        foreach ($tables as $table) {
            try {
                match ($driver) {
                    'pgsql' => DB::statement('SELECT setval(pg_get_serial_sequence(?, ?), 1, false)', ['"'.$table.'"', 'id']),
                    'sqlite' => DB::table('sqlite_sequence')->where('name', $table)->delete(),
                    'mysql', 'mariadb' => DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = 1'),
                    default => null,
                };
            } catch (QueryException) {
                // A table without an id sequence: nothing to restart.
            }
        }
    }
}
