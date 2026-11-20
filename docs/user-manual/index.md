---
title: User Manual
description: Install MylibSQLAdmin, understand the four roles, and run teams, groups, databases, tokens and Database Studio end to end.
section: Introduction
eyebrow: Product guide
---

MylibSQLAdmin is a web control plane for [`libsql-server`](https://github.com/tursodatabase/libsql): it gives a team a browser UI for the databases, credentials and people that would otherwise live in shell scripts and `curl` calls.

This manual is organised around **what you are allowed to do**, not around the menu bar. Each role has its own guide with the screens it can actually reach, the actions it can actually perform, and the boundaries it cannot cross.

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/dashboard.png" alt="MylibSQLAdmin dashboard showing three databases and usage metrics">
    <figcaption>The dashboard as a Super Admin sees it: every database in the team, with live query and storage metrics.</figcaption>
</figure>

## How access is modelled

Three layers decide what you see after you log in. They are independent, and confusing them is the fastest way to give someone the wrong access.

1. **Your role** — one of Super Admin, Team Manager, Database Maintainer or Member. A role grants a fixed set of permissions and is assigned by a Super Admin.
2. **Your team membership** — every non-platform object (group, database, token, invitation) belongs to a team. Membership is stored per team, so the same person can hold different positions in different teams.
3. **Database grants** — for Maintainers and Members, visibility is not implied by the role. A database appears in their list only after it has been explicitly granted to them.

Server-side policies enforce all three. The UI hides what you cannot use, but hiding a button is not the security boundary — the policy is.

## Start here

<div class="card-grid">
    <a class="card" href="{{ site.baseurl }}/user-manual/getting-started/">
        <span class="card__title">Getting started</span>
        <span class="card__desc">Install with the installer script, log in with the bootstrap credentials, and create your first database.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/roles/">
        <span class="card__title">Roles at a glance</span>
        <span class="card__desc">The four roles, what each one is for, and the permission matrix behind them.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/guides/databases/">
        <span class="card__title">Databases &amp; groups</span>
        <span class="card__desc">Create, group, grant, archive and restore libSQL databases.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/guides/tokens/">
        <span class="card__title">Tokens</span>
        <span class="card__desc">Full-access and read-only credential pairs, shown once and hashed at rest.</span>
    </a>
</div>

## Conventions in this manual

- Screenshots come from a real installation with demo data — a team called **Acme Engineering**, two groups, three databases, and one user per role.
- Commands are shown for a Docker Compose installation, which is the default. Kiri users can substitute `kiri compose` where the compose workflow is used.
- Anything marked <span class="role-badge">Super Admin</span> requires that role; the role guides spell out the exact boundary for everyone else.

<div class="callout callout--note">
    <span class="callout__title">Version</span>
    <p>This manual describes MylibSQLAdmin <strong>1.6.x</strong>. Pin container images by version tag — the <code>latest</code> tag moves on every publish.</p>
</div>
