<?php

return [
    /*
     * Upload ceilings for sponsor media, in megabytes. Ads are only
     * displayed (never played with sound or clicked), so a small clip is
     * plenty and keeps phones from burning data on a banner. Override
     * per-environment via .env, never hardcode a second value elsewhere.
     */
    'max_image_mb' => (int) env('ADS_MAX_IMAGE_MB', 3),
    'max_video_mb' => (int) env('ADS_MAX_VIDEO_MB', 8),

    /*
     * Banner sponsors do not stay on screen all the time: a banner shows
     * when the page opens, folds away after `banner_visible_seconds`, and
     * comes back after `banner_hidden_seconds` (then repeats). Set
     * banner_hidden_seconds to 0 to keep banners visible permanently.
     */
    'banner_visible_seconds' => (int) env('ADS_BANNER_VISIBLE_SECONDS', 8),
    'banner_hidden_seconds' => (int) env('ADS_BANNER_HIDDEN_SECONDS', 30),

    /*
     * The sponsor pop-up on the player auction page: it opens by itself
     * `popup_first_seconds` after the page opens, stays for
     * `popup_visible_seconds`, then comes back every `popup_interval_seconds`
     * (counted from when it closes). Set the visible or the interval seconds
     * to 0 to switch the pop-up off.
     */
    'popup_first_seconds' => (int) env('ADS_POPUP_FIRST_SECONDS', 20),
    'popup_visible_seconds' => (int) env('ADS_POPUP_VISIBLE_SECONDS', 8),
    'popup_interval_seconds' => (int) env('ADS_POPUP_INTERVAL_SECONDS', 120),
];
