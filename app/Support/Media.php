<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Where a picture comes from, with a safe answer when it is not there.
 *
 * Every picture the site shows (a player's photo, a team's logo, a news
 * cover...) is a path on the 'public' disk. When there is no path, or the
 * file is not on the disk any more, the page must still look right — never a
 * broken-image icon, never the alt text — so it shows one of the two default
 * pictures kept in public/images:
 *
 *  - 'image' — default.png, for anything that is not a person;
 *  - 'user'  — default-user.jpeg, for a person (a player, a contributor).
 *
 * On a remote bucket the file is not checked server-side (see existingUrl()). The browser-side half of this (a picture that exists but fails to load)
 * is the small script in layouts/partials/image-fallback.blade.php.
 */
class Media
{
    public const DEFAULT_IMAGE = 'images/default.png';

    public const DEFAULT_USER = 'images/default-user.jpeg';

    public static function defaultUrl(string $kind = 'image'): string
    {
        return asset($kind === 'user' ? self::DEFAULT_USER : self::DEFAULT_IMAGE);
    }

    /**
     * The URL of a stored picture, or the default one for $kind.
     */
    public static function url(?string $path, string $kind = 'image'): string
    {
        return self::existingUrl($path) ?? self::defaultUrl($kind);
    }

    /**
     * The URL of a stored picture, or null when there is no path or no file.
     * For the few places where "no picture" must show nothing at all (the QR
     * code, the site logo) rather than a default picture.
     */
    public static function existingUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            // On a remote bucket (PUBLIC_DISK_DRIVER=s3) asking "does it exist?" is a network call per
            // picture, which would make every page slow. The saved path is trusted; a picture that
            // really is missing falls back to the default one in the browser (image-fallback script).
            if (config('filesystems.disks.public.driver') !== 'local') {
                return $disk->url($path);
            }

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
