---
title: Observability
nav: Observability
order: 3
---

# Observability

Three signals matter, and everything else is context for them: is money moving, is the queue keeping up, is the
provider healthy.

## Alerting metrics

| Metric | Warn | Page | Why it matters |
| --- | --- | --- | --- |
| `harbor_closing_lag_minutes` | 60 | 90 | Invoices going out late |
| `harbor_settlement_gap` | 50 | 200 | Payments taken but not recorded |
| `harbor_queue_depth{queue=billing}` | 5,000 | 20,000 | Retries and emails backing up |
| `harbor_webhook_failure_ratio` | 0.05 | 0.20 | Customers losing events |
| `harbor_provider_latency_p99` | 2s | 6s | Reservations timing out |
| `harbor_integrity_check` | — | any failure | Ledger disagrees with invoices |

## Tracing

Every request carries a trace ID from the edge through the queue. The queue link is manual — jobs serialise the
trace context on dispatch, because nothing propagates it for you across a Redis boundary.

### Finding one customer's story

Search by account rather than trace when you are reconstructing a complaint. One customer's problem is usually
several traces, and the account tag is on all of them.

## Logging

Structured, one event per line, no multi-line stack traces in production. Amounts are logged in minor units with
their currency, never formatted, so a log search for a value actually finds it.

> [!NOTE]
> Payment method details never reach the logs. The redaction layer strips them by field name, which means a new
> field carrying card data will leak until it is added to the list. Adding it is part of the change, not a follow-up.

## Dashboards

The on-call dashboard shows the six alerting metrics and nothing else. Detail dashboards exist per subsystem and
are linked from it, but the top-level view is deliberately narrow — six panels can be read in the ten seconds
between waking up and deciding whether this is real.
