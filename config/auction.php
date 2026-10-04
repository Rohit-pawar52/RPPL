<?php

return [
    /*
     * The public auction page is read by everyone in the hall (often behind
     * one Wi-Fi) every few seconds. The data behind it is built once and
     * kept for this many seconds, so a crowd costs the database almost
     * nothing; the page is therefore at most this far behind the console.
     * 0 turns the cache off.
     */
    'public_cache_seconds' => (int) env('AUCTION_PUBLIC_CACHE_SECONDS', 2),

    /*
     * How often the public page asks for the latest picture.
     */
    'public_poll_seconds' => (int) env('AUCTION_PUBLIC_POLL_SECONDS', 3),
];
