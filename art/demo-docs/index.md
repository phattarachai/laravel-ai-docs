---
title: Harbor Platform
nav: Welcome
order: 1
---

# Harbor Platform

Harbor is a subscription billing and customer platform. One account holds the customer record, the plans they
subscribe to, the invoices those subscriptions generate, and the payments that settle them.

This handbook is the working reference for the team building it. It lives beside the code and is versioned with it,
so a change to behaviour and the page describing that behaviour land in the same commit.

## Where to start

| If you are… | Read first |
| --- | --- |
| New to the codebase | [Request lifecycle](architecture/request-lifecycle.md) |
| Wiring an integration | [Authentication](integrations/authentication.md) |
| On call this week | [Runbooks](operations/runbooks.md) |
| Changing pricing | [Plans and subscriptions](billing/subscriptions.md) |

## The four moving parts

**Accounts** own everything. An account has one billing profile, one currency, and any number of members.

**Subscriptions** attach a plan to an account with a billing anchor — the day of the cycle the invoice is cut.

**Invoices** are generated from subscriptions plus metered usage, then finalised. A finalised invoice is immutable;
corrections go out as a credit note.

**Payments** settle invoices. A payment can cover part of an invoice, and one payment can span several.

## Conventions in this handbook

Money is stored in minor units as an integer, never a float. Timestamps are UTC in the database and rendered in the
account's timezone. Identifiers are prefixed and opaque: `acct_`, `sub_`, `inv_`, `pay_`.

> [!NOTE]
> Anything marked **planned** describes a decision that has been made but not shipped. If you find behaviour that
> contradicts a page, the code is right and the page is a bug — fix it in the same pull request.
