---
title: Domain events
nav: Domain events
order: 3
---

# Domain events

Events are the seam between the billing core and everything that reacts to it — email, analytics, outbound
webhooks, the customer portal cache.

## Naming

`resource.past_tense_verb`. The resource is singular, the verb describes something that already happened. There is
no `will_` or `about_to_` event, because a listener cannot veto anything.

## The catalogue

| Event | Fired when | Payload root | Consumed by |
| --- | --- | --- | --- |
| `subscription.created` | A plan is first attached | `sub_*` | Provisioning, email |
| `subscription.plan_changed` | A plan swap commits | `sub_*` | Provisioning, analytics |
| `subscription.entered_grace` | First payment failure | `sub_*` | Email, retries |
| `subscription.cancelled` | End of term or immediate | `sub_*` | Provisioning, analytics |
| `invoice.finalised` | Draft becomes open | `inv_*` | Email, webhooks |
| `invoice.paid` | Balance reaches zero | `inv_*` | Provisioning, webhooks |
| `payment.failed` | Provider declines | `pay_*` | Retries, email |

## Ordering guarantees

Events for one account are delivered in order. Events across accounts are not ordered relative to each other, and
nothing should depend on that.

### What "in order" costs

Per-account ordering is bought with a per-account queue key, which means a single account generating a burst of
events processes them serially. An account importing ten thousand historical usage records will lag. That is the
accepted trade — out-of-order billing emails are worse than slow ones.

## Listener rules

A listener must be idempotent. Delivery is at-least-once, and the retry policy will re-run a listener that threw
after doing half its work.

A listener must not write to the ledger. Only the pipeline writes to the ledger; a listener that needs a
financial effect enqueues a command instead.
