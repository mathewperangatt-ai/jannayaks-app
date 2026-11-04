<?php

/*
| --------------------------------------------------------------------------
| Automated encrypted backups to Cloudflare R2
| --------------------------------------------------------------------------
| Disabled by default (BACKUP_ENABLED=false). The scheduled command checks
| the flag at runtime, so deploying this config alone never starts backups.
|
| Destination credentials are dedicated, bucket-restricted R2 API tokens
| (BACKUP_R2_*) that differ from the live media credentials and can only
| access BACKUP_R2_BUCKET. Live media buckets are read through the
| application's existing per-bucket credentials.
|
| Archives are tar.gz, encrypted with age (X25519 recipient, ChaCha20-Poly1305,
| age spec v1) before upload. Only the PUBLIC age recipient key lives in
| Railway; the private identity stays with the owner, offline.
*/

return [

    'enabled' => (bool) env('BACKUP_ENABLED', false),

    /** Retention pruning is opt-in separately: enable only after the first backups and restore tests. */
    'prune_enabled' => (bool) env('BACKUP_PRUNE_ENABLED', false),

    /*
     | Backup destination disk (Storage::build). Production: dedicated R2
     | backup token + bucket. Tests override this array with a local disk.
     */
    'destination' => [
        'driver' => 's3',
        'key' => env('BACKUP_R2_ACCESS_KEY_ID'),
        'secret' => env('BACKUP_R2_SECRET_ACCESS_KEY'),
        'region' => env('BACKUP_R2_REGION', 'auto'),
        'bucket' => env('BACKUP_R2_BUCKET'),
        'endpoint' => env('BACKUP_R2_ENDPOINT', env('R2_ENDPOINT')),
        'use_path_style_endpoint' => env('BACKUP_R2_USE_PATH_STYLE_ENDPOINT', true),
        'throw' => true,
    ],

    /*
     | Live media disks (from config/filesystems.php) whose FULL contents are
     | copied into every archive — every object in the bucket, not only rows
     | referenced by current database records.
     */
    'media_disks' => ['r2', 'source_materials'],

    /** age public recipient (age1...). Backups fail closed when empty unless allow_plaintext is set. */
    'age_recipient' => env('BACKUP_AGE_RECIPIENT', ''),

    'age_binary' => env('BACKUP_AGE_BINARY', 'age'),

    /** Local/testing escape hatch only. Never enable in production. */
    'allow_plaintext' => (bool) env('BACKUP_ALLOW_PLAINTEXT', false),

    'pg_dump_binary' => env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),

    /** Single email address for failure notifications (optional). */
    'notification_email' => env('BACKUP_NOTIFICATION_EMAIL', ''),

    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 6),
    ],

    /** Symfony Process timeout (seconds) for dump / tar / age subprocesses. */
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 900),

    /** Slug used in archive names: {tier}/jannayaks-YYYYMMDD-HHMMSS.tar.gz.age */
    'archive_slug' => 'jannayaks',

];
