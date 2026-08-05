---
title: Authentication
nav: Authentication
order: 1
---

# Authentication

Every request carries an API key. There are no sessions, no cookies, and no OAuth dance for server-to-server use.

## Keys

A key belongs to one account and one environment. Live keys are prefixed `hk_live_`, test keys `hk_test_`, and the
prefix is checked before the lookup so a test key can never touch live data by accident.

```bash
curl https://api.harbor.example/v1/invoices \
  -H "Authorization: Bearer hk_live_9f2c4a7be31d" \
  -H "Harbor-Version: 2026-01-15"
```

The version header pins the response shape. Omitting it uses the account's default version, which is set to
whatever was current when the account was created and never moves on its own.

## Scopes

| Scope | Grants | Typical holder |
| --- | --- | --- |
| `read` | Every `GET` | Dashboards, exports |
| `write` | Mutations except keys and members | Application servers |
| `admin` | Everything, including key rotation | Provisioning scripts |

A key cannot create a key with a scope it does not itself hold.

## Rotation

Rotation issues a new key and marks the old one for expiry. Both work during the overlap window, which defaults to
24 hours.

```js
const { key, expiresAt } = await harbor.keys.rotate('key_7c1a', {
    overlapHours: 72,
})

console.log(`old key stops working at ${expiresAt}`)
```

> [!WARNING]
> Revocation is immediate and has no overlap. It writes a tombstone the resolver checks on every request, so a
> revoked key stops working before the next request completes — including requests already in flight behind a slow
> upstream.

## Signing webhooks back

If you call Harbor from a webhook handler, pass the original event's `id` as your idempotency key. Harbor's own
retries will then collapse into one effect on your side.

### Clock skew

Signature verification allows five minutes of skew in either direction. A host whose clock drifts further will see
every webhook rejected, which is almost always the real cause of "webhooks stopped working overnight".
