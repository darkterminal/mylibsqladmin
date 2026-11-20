---
title: Database Studio
description: Query a libSQL database in the browser, with a token that is minted per visit and never persisted.
section: Guides
eyebrow: Query console
---

Database Studio is the embedded query console. It is the fastest way to inspect a schema, run a one-off query, or prove that a database is reachable before you wire an application to it.

<figure class="screenshot">
    <img src="/images/studio.png" alt="Database Studio with table list and SQL editor">
    <figcaption>Studio on a real database: the table list on the left, the SQL editor on the right.</figcaption>
</figure>

## Open Studio

1. Open **Databases**.
2. Press **Studio** on the row you want to query.
3. Wait for the table list to populate.

The URL carries the database name (`/database-studio?database=events`), so a Studio link can be bookmarked or shared with someone who has access to the same database.

## How the connection works

Studio is a three-part arrangement, and understanding it explains most failures:

1. The **parent page** (MylibSQLAdmin) mints a full-access token for the signed-in user by calling `POST /database-studio/token`.
2. The parent page opens the **embedded Studio** from `libsqlstudio.com` in an iframe.
3. Queries typed in the iframe are posted to the parent page, which executes them with `@libsql/client/web` against the database's sqld endpoint.

The token never reaches the iframe and is never written to the server. A fresh one is minted on every visit.

<div class="callout callout--note">
    <span class="callout__title">Access follows the database, not the role</span>
    <p>A Member can use Studio on a granted database even though they cannot mint tokens manually. Studio's token is a per-visit convenience, not a credential you can copy out.</p>
</div>

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Table list never loads; console shows a CSP `connect-src` violation | The browser is trying to reach an sqld host that the page's Content Security Policy does not allow | In non-production, the policy allows `http://*.localhost:8080`; use a `*.localhost` endpoint |
| `Failed to fetch` / connection refused to `http://<db>.db:8080` | `LIBSQL_HOST` points at the internal compose service name, which browsers cannot resolve | Set `LIBSQL_HOST` to the client-facing host (`localhost` for a local install, the public parent domain in production) |
| Studio works over HTTP but not HTTPS | Mixed content: an HTTPS page cannot open a plain-HTTP sqld endpoint | Put sqld behind TLS, or terminate TLS at the proxy |
| Studio opens but the table list is empty | The database has no tables yet | Create a table, or run `CREATE TABLE` in the editor |
| `Studio token could not be created` | The user has no access to the database, or the session expired | Check the grant, then sign in again |

The distinction that matters: `LIBSQL_HOST` is the **client-facing** host used by the browser, while the admin API uses `LIBSQL_API_HOST`. They are deliberately separate — the admin API is usually only reachable inside the container network.

## Run a query

Type SQL in the editor and run it. Results render as a grid below the editor. The connection is bound to the database you opened; switching databases means going back to the Databases table and opening Studio on another row.

<div class="callout callout--warn">
    <span class="callout__title">Studio is a console, not a migration tool</span>
    <p>There is no undo. Statements run immediately against the real database. Take a backup of the sqld volume before structural changes, and prefer testing destructive SQL on a throwaway database.</p>
</div>

## Related

- [Tokens](/user-manual/guides/tokens/)
- [Databases &amp; groups](/user-manual/guides/databases/)
