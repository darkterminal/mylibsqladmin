---
title: Getting started
description: Install MylibSQLAdmin, read the one-time bootstrap credentials, log in, and create a group, a database and a token.
section: Introduction
eyebrow: First run
---

This is the shortest path from an empty server to a working database you can query in Database Studio.

## Requirements

- Docker with the Compose plugin (or Kiri, if you prefer it locally)
- `openssl` on the host that runs the installer
- A directory you are happy to keep the stack in — the installer writes `compose.yml`, `.env` and `libsql-data/` there

## 1. Install

Run the installer inside the directory that should hold the stack:

```bash
curl --proto '=https' --tlsv1.2 -LsSf \
  https://raw.githubusercontent.com/darkterminal/mylibsqladmin/refs/heads/main/scripts/installer.sh | sh
```

The script asks a few questions (timezone, application name, local or remote sqld, image tag) and generates a `compose.yml` with three services: `webui`, `db` and `proxy`. It also creates `libsql-data/` and pins the container to your own uid and gid, so sqld can write to the volume without running as root.

<div class="callout callout--note">
    <span class="callout__title">Manual install</span>
    <p>Prefer to write the compose file yourself? The <a href="https://github.com/darkterminal/mylibsqladmin/blob/main/docs/README.md">installation guide</a> documents the LLI (local) and LRI (remote) layouts, the proxy ports, and the environment variables each service needs.</p>
</div>

## 2. Read the bootstrap credentials — once

On a fresh install the container runs `php artisan migrate --force` at start-up. When `LIBSQL_SEED_DEFAULTS=true`, the bootstrap seeder creates the first Super Admin and writes its credentials to:

```
storage/app/bootstrap-credentials.txt
```

Read the file, store the password in your password manager, then delete the file. It is created with mode `0600` and is never written again — there is no "show me the password" screen later.

<div class="callout callout--warn">
    <span class="callout__title">Delete it</span>
    <p>The bootstrap file is a one-time hand-off, not a credential store. Delete it as soon as the first login succeeds. If you lose the password before saving it, reset it from the CLI with <code>php artisan tinker</code> rather than re-seeding.</p>
</div>

## 3. Log in

Open the app (the installer prints the URL and port) and sign in with the seeded Super Admin.

<figure class="screenshot">
    <img src="/images/login.png" alt="MylibSQLAdmin login screen with username and password fields">
    <figcaption>The login screen. Authentication is by username, not email.</figcaption>
</figure>

After login you land on the dashboard. A brand-new install has no databases, so the empty state is expected.

## 4. Create a group and a database

A **group** is a way to share one credential across several databases. A **database** is the actual libSQL namespace — it becomes a directory on the sqld volume.

1. Open **Groups** in the sidebar and choose **New group**. Name it something meaningful, for example `analytics`.
2. Choose **New Database** in the header. Pick the group, enter a database name (`events`), and leave **Schema Database** unchecked unless you are deliberately creating a shared-schema database.
3. Submit. The namespace is created on sqld immediately — it is not a UI-only record.

<figure class="screenshot">
    <img src="/images/groups.png" alt="Groups page with the analytics group selected and its databases listed">
    <figcaption>A group with its databases. Each database can be opened in Studio, connected to over the SDK, or tokenised independently.</figcaption>
</figure>

Database names may contain letters, digits, dashes and underscores. Pick the name you want to live with: it becomes the subdomain used by the proxy (`events.localhost:8080` in local installs).

## 5. Mint a token and open Studio

Open **Tokens**, choose **Generate New Token**, select the database, optionally name the token and set an expiry. The full-access and read-only pair is displayed exactly once.

To query the database from the browser, open **Databases** and press **Studio** on the row. Studio mints a short-lived full-access token for your own account on every visit; you never copy it by hand.

<figure class="screenshot">
    <img src="/images/studio.png" alt="Database Studio showing tables and a SQL editor">
    <figcaption>Database Studio, embedded from libsqlstudio.com, querying a real libSQL database through the proxy.</figcaption>
</figure>

## 6. Add people

Create users and assign roles from **User Management** (Super Admin only), then add them to a team from **Teams**. Members and Maintainers also need an explicit database grant before the database appears in their list.

<figure class="screenshot">
    <img src="/images/teams.png" alt="Teams page showing the Acme Engineering team, its members, groups and databases">
    <figcaption>A team with four members, two groups and three databases. Membership is per team.</figcaption>
</figure>

## Where to go next

- Read [Roles at a glance](/user-manual/roles/) before handing out accounts.
- Read [Databases & groups](/user-manual/guides/databases/) for archiving, restoring and granting access.
- Read [Tokens](/user-manual/guides/tokens/) for the full credential lifecycle.
