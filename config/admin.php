<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap administrator
    |--------------------------------------------------------------------------
    |
    | There is deliberately no built-in admin login and no default password.
    | `php artisan rppl:ensure-admin --lock-demo-accounts` (run on every start
    | by docker/start.sh) creates the first administrator from these values,
    | and only when the site has no usable administrator: an active admin whose
    | password is not one of the published demo/default ones. A usable admin is
    | never touched.
    |
    |   ADMIN_EMAIL     the login email (stored in lower case)
    |   ADMIN_PASSWORD  at least `password_min_length` characters. Keep it in
    |                   the hosting dashboard's environment, never in git.
    |   ADMIN_NAME      the account's display name
    |
    | RESET_ADMIN_PASSWORD is the break-glass switch for a lost password: while
    | it is true, every run makes ADMIN_EMAIL an active admin with ADMIN_PASSWORD
    | (creating the user if needed), overriding any password changed since. Set
    | it for ONE start and remove it again. Only true/1/yes/on switch it on.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

    'name' => env('ADMIN_NAME', 'Administrator'),

    'reset_password' => filter_var(env('RESET_ADMIN_PASSWORD', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | The shortest password accepted for the bootstrap administrator and when a
    | signed-in user changes their own password.
    */
    'password_min_length' => 12,

    /*
    | A login that still has a PUBLISHED password (App\Support\PublicLogins: the demo logins and the
    | old admin@gmail.com / 12345678) may open only the Change password page until it has changed it.
    | On by default in production, off elsewhere so the demo logins keep working on a development
    | machine. Set ADMIN_FORCE_PRIVATE_PASSWORD=true/false to override either way.
    */
    'force_private_password' => filter_var(env('ADMIN_FORCE_PRIVATE_PASSWORD', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),

];
