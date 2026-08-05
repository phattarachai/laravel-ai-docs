---
title: Data model
nav: Data model
order: 2
---

# Data model

Nine tables carry the whole product. Everything else is a projection, a cache, or a queue.

## Core tables

| Table | Primary key | Owns | Cardinality | Soft deletes | Notable index |
| --- | --- | --- | --- | --- | --- |
| `accounts` | `acct_*` | Billing identity, currency, timezone | Root | Yes | `(status, created_at)` |
| `members` | `mem_*` | People with access to an account | Many per account | Yes | `(account_id, email)` unique |
| `plans` | `plan_*` | Named, versioned prices | Global | No | `(code, version)` unique |
| `subscriptions` | `sub_*` | Plan attachment, anchor, state | Many per account | No | `(account_id, state)` |
| `usage_records` | `use_*` | Metered quantities, append-only | Many per subscription | No | `(subscription_id, period_start)` |
| `invoices` | `inv_*` | Immutable once finalised | Many per account | No | `(account_id, state, due_at)` |
| `invoice_lines` | `il_*` | One charge, one tax treatment | Many per invoice | No | `(invoice_id)` |
| `payments` | `pay_*` | Provider settlement records | Many per account | No | `(provider_ref)` unique |
| `ledger_entries` | `led_*` | Double-entry, append-only | Many per account | No | `(account_id, posted_at)` |

## Invariants the schema enforces

### One open invoice per subscription period

A partial unique index on `invoices` covers `(subscription_id, period_start)` where `state = 'draft'`. Two workers
racing to open the same period get one winner and one constraint violation, which the job treats as success.

### The ledger never moves

`ledger_entries` has no `updated_at` and no update policy. A correction is a new pair of entries that reverse the
original. Any code path that needs to change a posted amount is wrong by construction.

### Money columns are integers

Every amount column is `bigint` in minor units with a sibling `currency` column. There is no `decimal` anywhere in
the schema, and the application layer refuses to construct an amount without a currency.

## States

| Entity | States | Terminal |
| --- | --- | --- |
| `subscriptions` | `trialing`, `active`, `grace`, `paused`, `cancelled` | `cancelled` |
| `invoices` | `draft`, `open`, `paid`, `uncollectible`, `void` | `paid`, `void`, `uncollectible` |
| `payments` | `pending`, `settled`, `failed`, `refunded` | `refunded` |

> [!NOTE]
> `paused` does not stop usage collection. Usage keeps accruing and is billed on resume, because pausing is a
> billing decision and metering is a product decision.
