<?php

namespace App\Support;

/**
 * Accepts only the kind of link a Google Form file-upload answer contains
 * (https://drive.google.com/open?id=…). A registration stores such links
 * for the photo / payment screenshot it could not import, and the admin
 * screens render them as clickable anchors — so anything that is not a
 * plain https link on a Google-owned host (javascript:, http:, a look-alike
 * domain, an address with embedded credentials) is refused up front, both
 * when a CSV is imported and when an admin types one in.
 */
final class DriveLink
{
    public const MAX_LENGTH = 512;

    public static function isValid(?string $url): bool
    {
        if ($url === null || $url === '' || strlen($url) > self::MAX_LENGTH) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        foreach (['google.com', 'googleusercontent.com'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first valid link in a spreadsheet cell. A cell can hold several
     * links (a form that allows more than one file separates them with a
     * comma); only the first is kept.
     */
    public static function first(?string $cell): ?string
    {
        foreach (preg_split('/[\s,;]+/', trim((string) $cell), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $candidate) {
            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
