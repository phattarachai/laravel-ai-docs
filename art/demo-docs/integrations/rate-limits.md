---
title: Rate limits
nav: Rate limits
order: 3
---

# Rate limits

Limits are per account over a sliding 60-second window. Issuing more keys does not raise them.

## Buckets

| Bucket | Endpoints | Requests / minute | Burst |
| --- | --- | --- | --- |
| `read` | All `GET` | 1,200 | 200 |
| `write` | Mutations | 300 | 50 |
| `usage` | `POST /v1/usage` | 6,000 | 1,000 |
| `export` | Report generation | 10 | 0 |

The `usage` bucket is deliberately generous — metering is high-volume by nature and back-pressure there loses data
rather than delaying it.

## Reading the headers

```bash
curl -i https://api.harbor.example/v1/invoices -H "Authorization: Bearer hk_live_..."

HTTP/2 200
harbor-ratelimit-bucket: read
harbor-ratelimit-limit: 1200
harbor-ratelimit-remaining: 1187
harbor-ratelimit-reset: 41
```

`reset` is seconds until the window rolls, not a timestamp.

## When you are throttled

A `429` carries `Retry-After` in seconds. Respect it — retrying sooner extends the window rather than shortening it,
because rejected requests still count.

> [!WARNING]
> Do not retry a `429` on a write endpoint without reusing the original idempotency key. A retry with a fresh key
> is a second charge, and the rate limiter is not what will stop it.

### Backoff that works

Full jitter, capped at the `Retry-After` value:

```js
const wait = Math.random() * Math.min(2 ** attempt * 1000, retryAfter * 1000)
```

Fixed backoff synchronises every client on the same schedule and produces a thundering herd exactly one window
later.
