<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Logins whose passwords are PUBLISHED: written in the README, set by the demo seeder (the dataset a new
 * Render database is filled with) and, for admin@gmail.com, by an earlier version of docker/start.sh that
 * already ran on deployed sites. Anybody who has read the repository can sign in with these, so a login
 * that still has its published password is never a real login (see EnsureAdminCommand, which switches
 * them off, and EnsurePasswordIsPrivate, which makes the person who signs in change the password first).
 */
final class PublicLogins
{
    /**
     * Lower-case email => the password published for it.
     *
     * @var array<string, string>
     */
    public const PAIRS = [
        'admin@rppl.test' => 'password',
        'scorer@rppl.test' => 'password',
        'admin@gmail.com' => '12345678',
    ];

    /**
     * Whether the email is one of the published ones. Cheap (no hashing): use it before usesPublicPassword().
     */
    public static function listsEmail(string $email): bool
    {
        return array_key_exists(Str::lower($email), self::PAIRS);
    }

    /**
     * Whether this user has a published email AND still the password published for it. A published
     * email whose password has been changed is a normal login, and so is any other email.
     */
    public static function usesPublicPassword(User $user): bool
    {
        $published = self::PAIRS[Str::lower($user->email)] ?? null;

        return $published !== null && Hash::check($published, $user->password);
    }
}
