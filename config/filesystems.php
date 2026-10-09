<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            // PRIVATE_DISK_ROOT / PUBLIC_DISK_ROOT exist so a throwaway copy of the app
            // (screenshots, experiments) can write its uploads somewhere that is not the
            // real storage folder. Leave them unset in a real environment.
            'root' => env('PRIVATE_DISK_ROOT', storage_path('app/private')),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Every picture the site shows lives on this disk. By default it is the local storage/app/public
        // folder. On a host whose disk is wiped on every restart (Render's free plan) set
        // PUBLIC_DISK_DRIVER=s3 and the PUBLIC_S3_* variables to keep uploads in any S3-compatible bucket
        // (Supabase Storage, Cloudflare R2, Backblaze B2 ...) instead; unset them again and mount a
        // persistent disk when moving to a paid plan. Nothing else in the code changes either way.
        'public' => env('PUBLIC_DISK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('PUBLIC_S3_KEY'),
                'secret' => env('PUBLIC_S3_SECRET'),
                'region' => env('PUBLIC_S3_REGION', 'us-east-1'),
                'bucket' => env('PUBLIC_S3_BUCKET'),
                'endpoint' => env('PUBLIC_S3_ENDPOINT'),
                // The public address files are served from (the bucket's public base URL, no trailing slash).
                'url' => rtrim((string) env('PUBLIC_S3_URL'), '/'),
                'use_path_style_endpoint' => (bool) env('PUBLIC_S3_PATH_STYLE', true),
                // A failed upload must be an error, not a saved path that points at nothing.
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => env('PUBLIC_DISK_ROOT', storage_path('app/public')),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
