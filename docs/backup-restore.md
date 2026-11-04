# Jannayaks — Automated Encrypted Backups (Cloudflare R2)

Runs nightly on the existing Railway `scheduler` service:
`php artisan backup:run` at **03:15 IST** (`routes/console.php`).

## What each backup contains

`{tier}/jannayaks-YYYYMMDD-HHMMSS.tar.gz.age`

Inside the age-encrypted tar.gz:

- `database.pgdump` — PostgreSQL **custom-format** dump (`pg_dump --format=custom
  --no-owner --no-privileges`), restorable with `pg_restore`. Transactionally
  consistent (MVCC snapshot).
- `media/r2/...` — every object in the `jannayaks-media` bucket (profile and
  In Memoriam photographs), original keys preserved.
- `media/source_materials/...` — every object in the private
  `jannayaks-source-materials` bucket (applicant documents).
- `manifest.json` — format version, timestamps, per-file sizes and SHA-256
  checksums for the dump and every media object. Contains no secrets, keys or
  environment values.

The archive is encrypted with **age (X25519 recipient, ChaCha20-Poly1305,
age spec v1)** before upload, then verified by streaming the uploaded object
back and comparing size + SHA-256. A failed upload or verification is deleted
and reported; previous backups are never touched by a failure.

Tiering: the daily run promotes the verified archive to `weekly/` on Sundays
and `monthly/` on the 1st (bucket-side copies, no re-upload). Retention
pruning (7 daily / 4 weekly / 6 monthly, `config/backup.php`) only deletes
objects matching the strict `jannayaks-YYYYMMDD-HHMMSS` pattern inside the
backup bucket, and only when `BACKUP_PRUNE_ENABLED=true`.

## Environment variables (Railway, `jannayaks-app` + `scheduler` services)

| Variable | Purpose |
|---|---|
| `BACKUP_ENABLED` | `false` by default; the schedule and command run but skip when false |
| `BACKUP_R2_BUCKET` | `jannayaks-backups` |
| `BACKUP_R2_ACCESS_KEY_ID` / `BACKUP_R2_SECRET_ACCESS_KEY` | dedicated token restricted to the backup bucket |
| `BACKUP_AGE_RECIPIENT` | **public** age key (`age1...`) |
| `BACKUP_NOTIFICATION_EMAIL` | failure notifications |
| `BACKUP_PRUNE_ENABLED` | enable retention pruning only after restore tests pass |

## Encryption key management (owner procedure)

Railway stores only the PUBLIC recipient key. Generate the pair locally:

```
# Windows: download age + age-keygen from https://github.com/FiloSottile/age/releases
age-keygen -o jannayaks-backup-key.txt
# prints: Public key: age1...   <- put ONLY this in Railway (BACKUP_AGE_RECIPIENT)
```

Keep `jannayaks-backup-key.txt` (starts `AGE-SECRET-KEY-1...`) offline —
password manager and/or printed copy. **It is the only way to decrypt
backups**; it never enters Railway, Git, or the backup bucket. Losing it
makes all backups undecryptable.

## Restoration (isolated environment only)

```bash
# 1. Fetch + decrypt (requires the private identity)
railway ssh --service scheduler -- "php /app/artisan backup:download \
    daily/jannayaks-20261105-031500.tar.gz.age \
    --identity=jannayaks-backup-key.txt --output=restore.tar.gz"
# (or any machine with the age binary: age --decrypt -i key.txt -o restore.tar.gz <file>)

# 2. Inspect + extract
tar -tzf restore.tar.gz        # verify contents
mkdir restore && tar -xzf restore.tar.gz -C restore
sha256sum restore/database.pgdump   # must equal manifest.json database.sha256

# 3. Restore PostgreSQL into an ISOLATED database (never production)
createdb jannayaks_restore_test
pg_restore --no-owner --dbname=jannayaks_restore_test restore/database.pgdump

# 4. Verify: row counts, spot-check records; then verify media checksums
#    against manifest.json media[].sha256

# 5. Media restore into temporary storage: copy restore/media/r2/... back to a
#    scratch bucket or local disk by original key. Never write into the live
#    jannayaks-media bucket during a test.
```

## Safety notes

- Live media buckets are only ever **read** (with the application's existing
  per-bucket credentials). The backup token cannot access them.
- Backups fail closed without `BACKUP_AGE_RECIPIENT` (plaintext uploads are
  only possible with `BACKUP_ALLOW_PLAINTEXT=true`, a testing flag).
- Temporary files live under `storage/app/backup-tmp/` and are always cleaned
  up, including on failure.
