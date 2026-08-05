---
title: Webhooks
nav: Webhooks
order: 2
---

# Webhooks

Harbor posts JSON to your endpoint when something happens. Delivery is at-least-once, ordered per account, and
signed.

## Verifying a delivery

```php
use Illuminate\Http\Request;

public function handle(Request $request): Response
{
    $signature = $request->header('X-Harbor-Signature');
    $expected  = hash_hmac('sha256', $request->getContent(), config('harbor.webhook_secret'));

    abort_unless(hash_equals($expected, (string) $signature), 401);

    ProcessHarborEvent::dispatch($request->json()->all());

    return response()->noContent();
}
```

Acknowledge fast and process off the request. Harbor's delivery timeout is five seconds; anything slower counts as
a failure and enters the retry schedule.

## Retry schedule

| Attempt | Delay | Cumulative |
| --- | --- | --- |
| 1 | Immediate | 0 |
| 2 | 30 seconds | 30s |
| 3 | 2 minutes | 2m 30s |
| 4 | 10 minutes | 12m 30s |
| 5 | 1 hour | 1h 12m |
| 6 | 6 hours | 7h 12m |
| 7 | 24 hours | 31h 12m |
| 8 | 24 hours | 55h 12m |

After the eighth failure the endpoint is disabled and an operator is emailed. Re-enabling replays nothing — use the
events API to backfill.

## Payload shape

```js
{
  id: 'evt_2Kq8xn',
  type: 'invoice.paid',
  created: 1771027200,
  account: 'acct_4Wm1',
  data: {
    object: {
      id: 'inv_8420',
      state: 'paid',
      total: 12900,
      currency: 'USD',
    },
    previous: { state: 'open' },
  },
}
```

`previous` carries only the fields that changed. It is absent on creation events.

> [!TIP]
> Subscribe to the narrowest set of event types you actually handle. An endpoint subscribed to everything spends
> most of its budget on `HTTP 200` responses to events it discards, and those still count toward your delivery
> concurrency.
