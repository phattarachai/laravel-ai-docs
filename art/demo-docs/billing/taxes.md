---
title: Tax handling
nav: Tax
order: 4
---

# Tax handling

Harbor computes tax at finalisation, from the billing profile's address and the line's tax category. It does not
compute tax on drafts, because a draft's address can still change.

## Categories

Every line carries a tax category. There are four, and adding a fifth is a schema change plus a provider mapping,
not a config edit.

| Category | Applies to | Typical treatment |
| --- | --- | --- |
| `saas` | Subscription and seat lines | Standard rate at the customer's location |
| `usage` | Metered lines | Follows `saas` in most jurisdictions |
| `service` | One-off professional services | Sometimes exempt, sometimes withheld at source |
| `exempt` | Credits, goodwill, write-offs | Never taxed |

## Reverse charge

When the customer supplies a validated business tax identifier in a jurisdiction that supports it, the line is
zero-rated and the invoice carries the reverse-charge note.

Validation is cached for 30 days. An identifier that fails validation is *not* treated as absent — the invoice is
held and an operator is notified, because silently charging tax on a valid business is a support ticket and
silently not charging is a liability.

## Rounding

Tax is computed per line and rounded half-up to minor units per line, then summed. Computing on the total and
allocating back produces off-by-one differences against provider statements.

```php
$tax = collect($invoice->lines)
    ->map(fn (Line $line): int => TaxRate::for($line)->applyTo($line->amount))
    ->sum();
```

> [!NOTE]
> Historical rates are stored on the invoice line, not looked up at render time. An invoice printed two years later
> shows the rate that was actually charged.
