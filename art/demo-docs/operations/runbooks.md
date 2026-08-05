---
title: Runbooks
nav: Runbooks
order: 2
---

# Runbooks

What to do when a specific alert fires. Each entry assumes you have a shell and the dashboard open and nothing else.

## Invoice closing job is behind

**Symptom** — `harbor_closing_lag_minutes` above 90.

The closing job is hourly and each run handles the accounts whose anchor falls in that hour. Lag means a run
overran, usually because one account has an unusual number of usage records.

```bash
php artisan harbor:closing:inspect --lagging
```

The output lists account, period, and record count. An account above roughly 200,000 usage records in one period
needs the chunked closer, which is a per-account flag:

```bash
php artisan harbor:closing:flag acct_4Wm1 --chunked
```

## Webhook endpoint disabled

**Symptom** — a customer reports missing events; the endpoint shows `disabled`.

Eight consecutive failures disables an endpoint. Re-enabling does not replay. Backfill from the events API, then
re-enable, in that order — re-enabling first means new deliveries interleave with the backfill and the customer
processes them out of order.

## Provider webhooks stopped

**Symptom** — payments settle at the provider but stay `pending` in Harbor.

Almost always the signing secret rotated at the provider without being rotated here. Check the rejection log
before anything else:

```bash
php artisan harbor:provider:log --rejected --since=1h
```

A wall of signature failures confirms it. If the log is empty, the provider is not calling at all and this is an
upstream incident.

> [!IMPORTANT]
> Never manually mark a payment as settled to clear an alert. Run the reconciler — it reads the provider's own
> settlement report and is the only path allowed to write a settlement.

```bash
php artisan harbor:reconcile --since=24h
```

## Ledger and invoice totals disagree

**Symptom** — the nightly integrity check fails.

Stop. Do not run a fix. This has never been a false positive, and the correct first action is to page the billing
lead and freeze finalisation:

```bash
php artisan harbor:finalisation:freeze --reason="integrity check failed"
```
