---
title: Deployment
nav: Deployment
order: 1
---

# Deployment

Deploys are rolling, zero-downtime, and gated on migrations having already run.

## The sequence

```bash
# 1. Migrations first, against the live database, on their own
php artisan migrate --force --isolated

# 2. Roll the web tier
kubectl rollout status deploy/harbor-web --timeout=300s

# 3. Drain and roll the workers
php artisan queue:restart
kubectl rollout status deploy/harbor-worker --timeout=600s

# 4. Verify
php artisan harbor:doctor --strict
```

Migrations run before the new code because the old code must survive the new schema for the length of the rollout.
That is a hard constraint on how migrations are written, not a preference.

## Expand and contract

Any schema change that removes or renames goes out as two deploys.

**Expand** — add the new column, backfill it, write to both. The old code ignores the new column and keeps working.

**Contract** — a deploy later, once nothing reads the old column, drop it.

> [!CAUTION]
> A single-deploy rename will take the site down for the duration of the rollout, because half the pods are running
> code that references a column the other half just dropped. There is no exception to this for "small" tables.

## Queue workers

Workers are drained, not killed. `queue:restart` sets a flag the worker checks between jobs, so an in-flight job
finishes before the process exits.

A job that runs longer than the pod's termination grace period will be killed mid-flight and retried. Anything
non-idempotent must therefore finish inside 120 seconds or checkpoint its progress.

## Rollback

Rolling back code is one command. Rolling back a migration is not supported in production — the contract deploy is
what removes things, and by then the expand deploy has been live long enough to be trusted.

```bash
kubectl rollout undo deploy/harbor-web
kubectl rollout undo deploy/harbor-worker
```
