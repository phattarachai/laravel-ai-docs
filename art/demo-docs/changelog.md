---
title: Changelog
order: 3
---

# Changelog

Behavioural changes that a reader of this handbook would otherwise have to discover from a diff. Library bumps and
refactors are not listed.

## 2.8 — Usage records are append-only

Metered usage can no longer be overwritten by re-posting the same idempotency key with a different quantity. A
correction is posted as a second record with a negative quantity, so the audit trail keeps both.

## 2.7 — Retry schedule moved to the account

The payment retry schedule used to be a global constant. It is now a column on the billing profile, defaulting to
the previous values, so a large account can be given a longer runway without a deploy.

## 2.6 — Credit notes replace invoice edits

Finalised invoices became immutable. The `invoices.update` endpoint now returns `409` for any invoice past draft.

## 2.5 — Webhook signatures

Every outbound webhook carries an `X-Harbor-Signature` header. The unsigned transport is removed, not deprecated.
