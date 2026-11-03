# IconsHerald Jannayaks Hosting — read-only integration

Server-to-server HMAC-authenticated endpoints on Jannayaks for the separate
IconsHerald Hosting module. Jannayaks remains the system of record for profile
data and photographs (Cloudflare R2). IconsHerald does not receive database or
R2 credentials.

## Environment

| Variable | Purpose |
|----------|---------|
| `JANNAYAKS_HOSTING_INTEGRATION_SECRET` | Shared HMAC secret (required). Empty → fail closed (HTTP 503). |
| `JANNAYAKS_HOSTING_INTEGRATION_TIMESTAMP_TOLERANCE` | Max signature age in seconds (default 300). |
| `JANNAYAKS_HOSTING_INTEGRATION_RATE_LIMIT` | Requests per minute per IP (default 60). Applied **before** HMAC checks. |
| `JANNAYAKS_HOSTING_INTEGRATION_SITEMAP_PER_PAGE` | Default page size (default 100). |
| `JANNAYAKS_HOSTING_INTEGRATION_SITEMAP_MAX_PER_PAGE` | Max page size (default 200). |

### Secret provisioning and rotation

1. Generate a cryptographically random secret (32+ bytes) offline.
2. Store it in Jannayaks production secrets as `JANNAYAKS_HOSTING_INTEGRATION_SECRET`.
3. Store the **same** value in IconsHerald Hosting’s secrets store.
4. Never log, commit, or return the secret.
5. To rotate: generate a new secret, update both systems in a short coordinated
   window, then discard the old value. Dual-secret acceptance is not implemented.

## Authentication

Headers (required on every request):

- `X-Jannayaks-Hosting-Timestamp` — Unix epoch **seconds** as a decimal integer string (e.g. `1730550000`). Digits only.
- `X-Jannayaks-Hosting-Signature` — **hex-encoded** HMAC-SHA256 (lowercase hex as produced by PHP `hash_hmac('sha256', …)`).

### Exact signed-string construction

UTF-8 string (four lines joined by `\n`, including a trailing body line that may be empty):

```text
{HTTP_METHOD}\n{PATH_WITH_QUERY}\n{TIMESTAMP}\n{RAW_BODY}
```

| Part | Rule |
|------|------|
| `HTTP_METHOD` | Uppercase method, e.g. `GET` |
| `PATH_WITH_QUERY` | Request path (`/internal/hosting/...`) plus `?` and the **raw query string bytes exactly as transmitted** when a query is present. The server verifies against the raw `QUERY_STRING` bytes it received — no sorting, no decoding, no re-encoding on either side. |
| `TIMESTAMP` | Same decimal string sent in `X-Jannayaks-Hosting-Timestamp` |
| `RAW_BODY` | Exact raw request body bytes. For normal GET requests this is empty (`""`). |

**Critical:** Sign the URI bytes you will send. Verification compares against the raw
query string bytes as received, so any change to parameter order, encoding
(`%20` vs `+`), or values after signing fails with HTTP 401. If an intermediary
(gateway, CDN, load balancer) rewrites the query string in flight, verification
fails closed the same way.

Comparison on Jannayaks uses `hash_equals`. Missing, invalid, or expired signatures → HTTP 401. Missing server secret → HTTP 503. Responses use `Cache-Control: no-store` (plus framework defaults).

### Replay window

Timestamps may differ from server time by at most
`JANNAYAKS_HOSTING_INTEGRATION_TIMESTAMP_TOLERANCE` seconds (default **300**).
There is no nonce: a captured valid signed GET can be replayed within that
window. Risk is limited to read-only access to data already intended for
IconsHerald Hosting. Tighten the tolerance or add nonces later if required.

### Worked example — paginated sitemap

Assume:

- Secret: `example-secret-do-not-use`
- Timestamp: `1730550000`
- Request: `GET /internal/hosting/sitemap-profiles?page=2&per_page=50`
- Body: empty

Canonical string (shown with visible newlines):

```text
GET
/internal/hosting/sitemap-profiles?page=2&per_page=50
1730550000

```

(the fourth line is empty)

```bash
# PHP equivalent of the signature:
php -r "echo hash_hmac('sha256', \"GET\\n/internal/hosting/sitemap-profiles?page=2&per_page=50\\n1730550000\\n\", 'example-secret-do-not-use');"
```

Send:

```http
GET /internal/hosting/sitemap-profiles?page=2&per_page=50 HTTP/1.1
Host: jannayaks.in
X-Jannayaks-Hosting-Timestamp: 1730550000
X-Jannayaks-Hosting-Signature: <hex from hash_hmac above>
```

If the client instead requested `per_page=50&page=2` (reordered), it must sign
that different `PATH_WITH_QUERY`; a signature for the original order will not
validate.

## Endpoints

### Profile

`GET /internal/hosting/profiles/{slug}`

Returns allowlisted public fields only when the profile is publicly visible,
`applications.hosting_destination = iconsherald`, and the owner is **not** an
internal demo (`%@jannayaks.internal`, same rule as the Jannayaks sitemap).
Otherwise HTTP 404.

### Sitemap list

`GET /internal/hosting/sitemap-profiles?page=1&per_page=100`

Paginated `{ slug, canonical_url, published_at }` entries for the same
eligibility set. `per_page` is clamped to the configured maximum.

## Photographs

Photo `url` values are existing Jannayaks public photo routes
(`profiles.public.photo`). Those routes keep publication and approval checks
and `Cache-Control: public, max-age=300` (up to five minutes of cache after
withdrawal).

Global `Cross-Origin-Resource-Policy` remains `same-origin`. For **IconsHerald-hosted,
publicly visible** profiles only, the photo response sets
`Cross-Origin-Resource-Policy: cross-origin` so IconsHerald pages may embed the
image. Jannayaks-hosted photos keep `same-origin`. Unpublished or unapproved
media still 404.
