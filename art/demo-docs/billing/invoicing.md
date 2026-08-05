---
title: Invoicing
nav: Invoicing
order: 2
---

# Invoicing

An invoice starts as a draft the moment a period opens, accumulates lines all period, and is finalised by the
closing job.

## Line composition

| Line type | Source | Proratable | Taxable | Appears on |
| --- | --- | --- | --- | --- |
| `subscription` | Plan price for the period | Yes | Yes | Every invoice |
| `seat` | Members above the included count | Yes | Yes | When over the limit |
| `usage` | Metered records for the period | No | Yes | When usage exists |
| `proration_credit` | Unused remainder of a swapped plan | — | Yes | Cycle after a change |
| `proration_charge` | New plan remainder | — | Yes | Cycle after a change |
| `discount` | Applied coupon | No | Before tax | While the coupon lives |
| `adjustment` | Manual correction, draft only | No | Yes | Rare, audited |

## Finalisation

Finalising freezes the line set, computes tax, assigns the sequential invoice number, and moves the state from
`draft` to `open`.

### The number is assigned last

Invoice numbers are gapless per account and per year, which means the number cannot be assigned until the invoice
is certain to exist. It is allocated inside the same transaction as the state change, from a per-account counter
row locked `FOR UPDATE`.

### Nothing is editable afterwards

`open` and every state beyond it reject writes to lines, amounts and tax. A correction is a credit note.

> [!IMPORTANT]
> The closing job is scheduled hourly, not daily, because anchors are per-account and spread across the clock. Do
> not "optimise" it to a nightly run — accounts anchored mid-day will invoice late.

## Credit notes

A credit note references exactly one invoice and cannot exceed its outstanding balance plus what has been paid.
Issuing one against a `void` invoice is refused.

```php
$note = CreditNote::issue($invoice, [
    'reason' => 'service_credit',
    'lines'  => [['description' => 'Goodwill credit', 'amount' => 2500]],
]);
```

The credit lands on the account balance and is consumed by the next invoice before any payment method is charged.

## Delivery

Finalisation queues the delivery job. Delivery failure never reverts finalisation — the invoice exists, it is
owed, and the customer can see it in the portal whether or not the email landed.
