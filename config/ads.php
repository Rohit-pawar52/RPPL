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
];
