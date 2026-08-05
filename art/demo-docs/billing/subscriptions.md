---
title: Plans and subscriptions
nav: Subscriptions
order: 1
---

# Plans and subscriptions

A subscription is a plan, an account, and an anchor. Everything else about it is derived.

## Plan versioning

Plans are immutable after publication. Changing a price publishes a new version and leaves existing subscribers on
the old one until they are explicitly migrated.

```php
$plan = Plan::publish([
    'code' => 'team',
    'interval' => 'month',
    'amount' => 4900,          // minor units
    'currency' => 'USD',
    'included_seats' => 10,
]);

$subscription = $account->subscribe($plan, anchor: 1);
```

The `anchor` is the day of the cycle the invoice closes. Passing `null` anchors to the subscription date.

## Changing plans mid-cycle

A plan change prorates by default. The unused remainder of the old plan is credited, the remainder of the new plan
is charged, and both land as lines on the next invoice rather than as an immediate charge.

```php
$subscription->changePlan($newPlan, prorate: true, invoiceNow: false);
```

Passing `invoiceNow: true` finalises an invoice for the proration immediately. Use it when the change is an upgrade
the customer expects to pay for at once.

### Downgrades

A downgrade takes effect at the end of the current period. The proration is a credit, and the credit is applied to
the next invoice, never refunded to the payment method.

> [!WARNING]
> A downgrade that reduces included seats below the seats actually in use is refused with `422`. Remove the seats
> first. Harbor does not silently deprovision members.

## Trials

A trial is a subscription in `trialing` state with no invoice. When the trial ends, the first invoice covers the
first full period — trials are never prorated.

| Setting | Default | Range |
| --- | --- | --- |
| Trial length | 14 days | 0–90 |
| Card required up front | No | — |
| Auto-cancel with no card | Yes | — |
| Reminder before end | 3 days | 0–14 |

## Pausing

A paused subscription stops invoicing and keeps serving. Usage continues to accrue and is billed on resume. A pause
longer than 90 days auto-cancels.
