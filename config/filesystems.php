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
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
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

        /*
        | Cloudflare R2 (S3-compatible) for public profile photographs.
        | Private source materials remain on private_uploads.
        | Credentials come only from environment variables.
        */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        'private_uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/private_uploads'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
        | Durable PRIVATE storage for applicant source materials (documents).
        | S3-compatible Cloudflare R2 bucket that MUST remain private: files are
        | only ever streamed through the policy-gated staff download route
        | (Staff/SourceMaterialDownloadController), never exposed by URL.
        | Opt-in per environment via SOURCE_MATERIALS_DISK (see config/online_interview.php);
        | when unset, local private_uploads stays the default. Each SourceMaterial row
        | records the disk it was stored on (storage_disk), so legacy local rows
        | keep downloading unchanged.
        */
        'source_materials' => [
            'driver' => 's3',
            'key' => env('R2_SOURCE_ACCESS_KEY_ID', env('R2_ACCESS_KEY_ID')),
            'secret' => env('R2_SOURCE_SECRET_ACCESS_KEY', env('R2_SECRET_ACCESS_KEY')),
            'region' => env('R2_SOURCE_REGION', 'auto'),
            'bucket' => env('R2_SOURCE_BUCKET'),
            'endpoint' => env('R2_SOURCE_ENDPOINT'),
            'url' => env('R2_SOURCE_URL'),
            'use_path_style_endpoint' => env('R2_SOURCE_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => true,
            'report' => false,
            'visibility' => 'private',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values will be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
