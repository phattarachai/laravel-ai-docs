---
title: Glossary
order: 2
---

# Glossary

The vocabulary the code, the API and the UI all speak. When a term here appears in a schema column or a class name,
it means exactly this and nothing looser.

## Account and identity

**Account** — the billing entity. Everything that can be charged hangs off one.

**Member** — a person with access to an account. Members are not customers; an account is the customer.

**Billing profile** — the address, tax identifier and default payment method attached to an account.

## Subscription terms

**Plan** — a named, versioned price. Plans are never edited after publication; a price change creates a new version.

**Billing anchor** — the day of the month or week the cycle closes. Set once at subscription time, preserved across
plan changes unless the caller explicitly resets it.

**Proration** — the partial-period credit or charge produced when a subscription changes mid-cycle.

**Grace period** — the window after a failed payment during which the subscription still serves traffic.

## Money terms

**Minor units** — the smallest denomination of the currency, as an integer. 1250 in USD is $12.50.

**Credit note** — a document that reduces the amount owed on a finalised invoice. Never a negative invoice.

**Settlement** — the moment a payment provider confirms funds moved, which is later than authorisation.

**Write-off** — an outstanding balance the business has decided not to pursue. Closes the invoice without a payment.
