<?php

return [
    /*
     * Maximum admin video upload size, in megabytes. These are expected
     * to be short (~1-2 minute) clips, not long-form video — 50MB is a
     * generous ceiling for that without allowing arbitrary large
     * uploads. Override per-environment via .env, never hardcode a
     * second value elsewhere.
     */
    'max_upload_mb' => (int) env('VIDEOS_MAX_UPLOAD_MB', 50),
];
