<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\PublicLogins;
use App\Support\UserSessions;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Makes sure the site has an administrator login without any built-in default password. Run by
 * docker/start.sh on every start, so everything here must be safe to repeat.
 *
 * A "usable" admin is an ACTIVE user with the admin role whose password is not one of the published
 * ones in PUBLIC_LOGINS: anybody who has read the README can sign in with those, so they are not a
 * real admin however many of them exist.
 *
 * - A usable admin exists: nothing is changed (the roles are only made sure to exist).
 * - None exists: one is created from config/admin.php (ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME).
 *   Missing or unusable values fail the command and create nothing - there is no fallback password.
 *   A user that already has ADMIN_EMAIL is never taken over or given a new password.
 * - RESET_ADMIN_PASSWORD=true (break-glass, for a lost password): ADMIN_EMAIL becomes an active admin
 *   with ADMIN_PASSWORD, the user being created if missing, and that user is signed out everywhere.
 * - --lock-demo-accounts: once a usable admin exists, every active published login that still has its
 *   published password is deactivated (never deleted: the demo data refers to them). Without a usable
 *   admin nothing is deactivated, so the site cannot lock itself out, and a warning says the logins
 *   are still open.
 *
 * No password is ever printed. The one from the environment is hashed straight away and only the hash
 * is passed on, so it cannot end up in an exception's stack trace either.
 */
class EnsureAdminCommand extends Command
{
    /**
     * Logins whose passwords are published (the list lives in PublicLogins, which the sign-in check
     * uses too, so the two can never disagree). Lower-case email => the published password.
     *
     * @var array<string, string>
     */
    public const PUBLIC_LOGINS = PublicLogins::PAIRS;

    protected $signature = 'rppl:ensure-admin
                            {--lock-demo-accounts : Deactivate the logins with published passwords (demo dataset, old default admin) once a usable admin exists}';

    protected $description = 'Create the first admin from ADMIN_EMAIL / ADMIN_PASSWORD when the site has no usable one (RESET_ADMIN_PASSWORD=true resets it)';

    public function handle(): int
    {
        // users.role_id is required, so the roles come first. Idempotent, and it never changes the
        // permissions of a role that already exists.
        app(RoleSeeder::class)->run();

        $roleId = (int) Role::where('slug', Role::ADMIN_SLUG)->value('id');
        $reset = (bool) config('admin.reset_password');

        $exitCode = ($reset || ! $this->usableAdminExists($roleId))
            ? $this->bootstrapAdmin($roleId, $reset)
            : $this->leaveExistingAdminAlone($roleId);

        if ($this->option('lock-demo-accounts')) {
            $this->lockPublicLogins($roleId);
        }

        return $exitCode;
    }

    private function bootstrapAdmin(int $roleId, bool $reset): int
    {
        $credentials = $this->credentials($reset);

        if ($credentials === null) {
            return self::FAILURE;
        }

        ['email' => $email, 'name' => $name, 'password' => $password] = $credentials;
        $passwordHash = Hash::make($password);
        unset($password, $credentials);

        return $reset
            ? $this->resetAdmin($roleId, $email, $name, $passwordHash)
            : $this->createFirstAdmin($roleId, $email, $name, $passwordHash);
    }

    private function leaveExistingAdminAlone(int $roleId): int
    {
        $this->info('A usable admin user already exists: nothing to do.');

        // ADMIN_EMAIL / ADMIN_PASSWORD only ever create the FIRST admin. When they name somebody
        // else, say so: on a host without a shell it is otherwise a puzzle why that login fails.
        $email = Str::lower(trim((string) config('admin.email')));

        if ($email !== '' && ! User::where('role_id', $roleId)->where('is_active', true)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->line('ADMIN_EMAIL ('.OutputFormatter::escape($email).') is not an active admin here and was NOT created: ADMIN_EMAIL / ADMIN_PASSWORD only create the first admin. To make it one anyway, set RESET_ADMIN_PASSWORD=true for a single start, then remove it.');
        }

        return self::SUCCESS;
    }

    private function createFirstAdmin(int $roleId, string $email, string $name, string $passwordHash): int
    {
        // Whoever already has this email (a scorer, an inactive admin, a demo login...) is not ours to
        // take over or to give a new password.
        if ($this->userWithEmail($email) !== null) {
            $this->toStderr(
                "A user with the email {$email} already exists, so no admin was created and that user was not changed.",
                'Use another ADMIN_EMAIL, or set RESET_ADMIN_PASSWORD=true for a single start to make that user an active admin with ADMIN_PASSWORD.',
            );

            return self::FAILURE;
        }

        DB::transaction(fn () => $this->newAdmin($roleId, $email, $name, $passwordHash));

        // Emails are compared exactly at login on PostgreSQL, so show the stored (lower-case) form.
        $this->info("Created the admin user {$email}. Sign in with exactly that email and ADMIN_PASSWORD.");

        return self::SUCCESS;
    }

    private function resetAdmin(int $roleId, string $email, string $name, string $passwordHash): int
    {
        $created = false;

        $user = DB::transaction(function () use ($roleId, $email, $name, $passwordHash, &$created) {
            $existing = $this->userWithEmail($email);

            if ($existing === null) {
                $created = true;

                return $this->newAdmin($roleId, $email, $name, $passwordHash);
            }

            // Break-glass: whatever the account was, it is an active admin with this password now.
            $existing->forceFill([
                'role_id' => $roleId,
                'is_active' => true,
                'password' => $passwordHash,
                'remember_token' => Str::random(60),
            ])->save();

            return $existing;
        });

        // Anybody already signed in as this user - who may be the reason for the reset - is signed out.
        UserSessions::forget($user);

        $this->toStderr(
            '!!! RESET_ADMIN_PASSWORD is ON: '.$user->email.($created ? ' was created' : ' was reset').' as an active admin with the ADMIN_PASSWORD. !!!',
            '!!! REMOVE RESET_ADMIN_PASSWORD from the environment now. While it stays set, every start resets this password again and undoes any password changed since. !!!',
        );

        return self::SUCCESS;
    }

    /**
     * Switches off the published logins, but only once somebody else can sign in as an admin.
     */
    private function lockPublicLogins(int $roleId): void
    {
        $accounts = $this->publicLoginAccounts()->where('is_active', true)->values();

        if ($accounts->isEmpty()) {
            $this->info('No active account uses a publicly known password.');

            return;
        }

        if (! $this->usableAdminExists($roleId)) {
            $this->toStderr(
                '!!! This site still has logins with publicly known passwords that anybody can sign in with: '.$accounts->pluck('email')->implode(', ').'. !!!',
                '!!! Nothing was deactivated, because no usable admin exists yet. Set ADMIN_EMAIL and ADMIN_PASSWORD (at least '.config('admin.password_min_length').' characters) in the environment and start again, or sign in as the admin and set a new password on the Change password page. !!!',
            );

            return;
        }

        DB::transaction(fn () => $accounts->each(fn (User $account) => $account->forceFill(['is_active' => false])->save()));

        foreach ($accounts as $account) {
            $this->line(OutputFormatter::escape("Deactivated {$account->email}: it still used a publicly known password"));
        }
    }

    /**
     * An active admin whose password is not a published one.
     */
    private function usableAdminExists(int $roleId): bool
    {
        return User::where('role_id', $roleId)
            ->where('is_active', true)
            ->get()
            ->contains(fn (User $user) => ! $this->usesPublicPassword($user));
    }

    /**
     * Every user (active or not) that has a published email and still its published password. The
     * email is matched in lower case, as an account made by hand may have capitals.
     *
     * @return Collection<int, User>
     */
    private function publicLoginAccounts(): Collection
    {
        return User::whereIn(DB::raw('LOWER(email)'), array_keys(self::PUBLIC_LOGINS))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user) => $this->usesPublicPassword($user));
    }

    private function usesPublicPassword(User $user): bool
    {
        return PublicLogins::usesPublicPassword($user);
    }

    /**
     * The usable ADMIN_* values, or null (after saying why on stderr) when they cannot be used.
     *
     * @return array{email: string, name: string, password: string}|null
     */
    private function credentials(bool $reset): ?array
    {
        $credentials = [
            'email' => Str::lower(trim((string) config('admin.email'))),
            'name' => trim((string) config('admin.name')) ?: 'Administrator',
            'password' => (string) config('admin.password'),
        ];

        $validator = Validator::make($credentials, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:'.config('admin.password_min_length')],
        ], [
            'required' => ':attribute is not set.',
            'email' => ':attribute is not a valid email address.',
            'max' => ':attribute must be at most :max characters.',
            'min' => ':attribute must be at least :min characters.',
        ], [
            'email' => 'ADMIN_EMAIL',
            'name' => 'ADMIN_NAME',
            'password' => 'ADMIN_PASSWORD',
        ]);

        if ($validator->passes()) {
            return $credentials;
        }

        $lines = [$reset
            ? 'RESET_ADMIN_PASSWORD is set but the admin login cannot be reset, nothing was changed:'
            : 'The site has no usable admin (an active admin whose password is not publicly known) and none can be created, nothing was created:'];

        foreach ($validator->errors()->all() as $error) {
            $lines[] = '  - '.$error;
        }

        if (! $reset) {
            $lines[] = 'Set ADMIN_EMAIL and ADMIN_PASSWORD (at least '.config('admin.password_min_length').' characters) in the environment, then start again.';
        }

        $this->toStderr(...$lines);

        return null;
    }

    private function newAdmin(int $roleId, string $email, string $name, string $passwordHash): User
    {
        // forceFill: email_verified_at is deliberately not mass assignable on the model.
        $user = (new User)->forceFill([
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->save();

        return $user;
    }

    /**
     * Case-insensitive: PostgreSQL compares emails exactly, and an account made by hand in the
     * admin panel may have capitals.
     */
    private function userWithEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [$email])->orderBy('id')->first();
    }

    /**
     * Problems go to stderr, where the host's log and a calling script's `|| echo` expect them.
     */
    private function toStderr(string ...$lines): void
    {
        $stderr = $this->output->getErrorStyle();

        foreach ($lines as $line) {
            $stderr->writeln('<error>'.OutputFormatter::escape($line).'</error>');
        }
    }
}
