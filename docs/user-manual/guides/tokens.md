---
title: Tokens
description: Full-access and read-only credential pairs, shown once, hashed at rest, revocable immediately.
section: Guides
eyebrow: Credentials
---

Every database credential in MylibSQLAdmin is issued as a **pair**: a full-access token that can write, and a read-only token that cannot. Both are generated together, shown once, and stored only as SHA-256 hashes.

<figure class="screenshot">
    <img src="/images/tokens.png" alt="Tokens table with database, access owner, token name, expiration and created date">
    <figcaption>The token list shows metadata only — a fingerprint, never the credential itself.</figcaption>
</figure>

## Generate a token

1. Open **Tokens** → **Generate New Token**.
2. Select the database. Only databases you can reach are listed.
3. Optionally name the token. Name it after the consumer (`etl-nightly`, `reporting-readonly`), not after the person — a token outlives a job title.
4. Select the user the token belongs to.
5. Set an expiration in days. `0` means no expiration.
6. Submit.

<figure class="screenshot">
    <img src="/images/tokens-reveal.png" alt="One-time reveal dialog with both token values blurred">
    <figcaption>The one-time reveal. This is the only moment both values exist in plaintext. Values are blurred here.</figcaption>
</figure>

Copy both values before dismissing the dialog. If you lose them, mint a new pair — there is no recovery path, by design.

## What is stored

| At creation | After the dialog closes |
|---|---|
| Full plaintext pair, returned once in the response | SHA-256 hash of each token |
| Name, database, owner, expiry | Same metadata, unchanged |
| — | A short fingerprint (`fp 000dd15c`) for identification |

The fingerprint is derived from the token and is safe to display. It lets you match a token in the UI to a credential in a secret store without exposing either.

## Expiry and revocation

- **Expiry** is counted in days from creation. An expired token stops authenticating; the row stays for audit purposes.
- **Revoke** (delete) takes effect immediately. There is no grace period and no cached session.
- Rotating a credential means: mint the new pair, update the consumer, revoke the old row. Do it in that order so the consumer never runs without a credential.

<div class="callout callout--note">
    <span class="callout__title">Read-only is enforced at the token</span>
    <p>The read-only token is a separate credential, not a permission flag on the full-access one. Give the read-only value to dashboards and analytics jobs; keep the full-access value for migrations and writes.</p>
</div>

## Database tokens vs group tokens

| | Database token | Group token |
|---|---|---|
| Scope | One database | Every database attached to a group |
| Minted by | Super Admin, Database Maintainer | Super Admin, Team Manager |
| Where | **Tokens** | Group detail |
| Use when | A consumer talks to exactly one namespace | Several databases share one application identity |

A Team Manager can see the team's database tokens but cannot mint them. That is why the group token is the credential a Team Manager actually owns.

## Tokens and Studio

Database Studio does not ask you to paste a token. On every visit it mints a short-lived full-access token for the signed-in user, hands it to the embedded Studio, and never persists the plaintext on the server. This is why opening Studio works for a Member even though the Tokens section is not part of their sidebar.

## Related

- [Databases &amp; groups](/user-manual/guides/databases/)
- [Database Studio](/user-manual/guides/studio/)
- [Permission matrix](/user-manual/reference/permissions/)
