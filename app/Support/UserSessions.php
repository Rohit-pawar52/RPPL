<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Signs a user out everywhere. Used when their password is changed or reset, so a session that was
 * opened with the old password - possibly by somebody who should never have had it - stops working.
 */
final class UserSessions
{
    /**
     * Delete every stored session of the user, this browser's included (the caller regenerates its
     * own session afterwards). Only the database session driver, which is what config/session.php
     * defaults to, can be searched by user; with any other driver this does nothing.
     *
     * @return int how many sessions were removed
     */
    public static function forget(User $user): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
