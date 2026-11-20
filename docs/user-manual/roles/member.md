---
title: Member
description: Use the databases shared with you — browse, query in Studio, and stay read-only by design.
section: Role guides
eyebrow: Role guide
---

<span class="role-badge">Member</span>

A Member consumes access. They query the databases shared with them, open Studio, and see who else is in the team. They do not create, grant, rotate or invite.

## What you see

- Dashboard
- Databases
- Groups
- Teams

There is **no Tokens entry** in a Member's sidebar. Credentials are handed to you by a Super Admin or a Database Maintainer; you use them, you do not mint them.

<figure class="screenshot">
    <img src="/images/member-dashboard.png" alt="Member dashboard showing only the database granted to the member">
    <figcaption>A Member's dashboard. One granted database means one database in the overview.</figcaption>
</figure>

## Story: query a shared database

### 1. Find the database

Open **Databases**. Only databases granted to you appear — the table is the same shape as an admin's, but without create, archive or grant actions.

<figure class="screenshot">
    <img src="/images/member-databases.png" alt="Member databases table with a single granted database and no create button">
    <figcaption>No <strong>New Database</strong> button in the header, no archive action on the row. The absence is the permission model made visible.</figcaption>
</figure>

### 2. Open Studio

Press **Studio** on the row. Studio mints a short-lived full-access token for your own account on every visit; you never see or copy it. The first screen lists the tables in the database.

<figure class="screenshot">
    <img src="/images/studio.png" alt="Database Studio showing tables and the SQL editor">
    <figcaption>Database Studio. The table list on the left is live; the editor on the right runs SQL against the database through the proxy.</figcaption>
</figure>

### 3. Run a query

Type SQL in the editor and run it. Results render as a grid. The connection is scoped to the database you opened — you cannot switch to a database you were not granted.

### 4. Check who else is in the team

Open **Teams** to see the team, its members and the groups you can view. This is read-only context, not a management screen.

<figure class="screenshot">
    <img src="/images/member-groups.png" alt="Member view of the groups page, without create or manage actions">
    <figcaption>Groups are visible to Members, but the create and manage actions are gone.</figcaption>
</figure>

## What you can do

- View databases granted to you.
- Open Database Studio and run SQL against them.
- View groups, group tokens (read-only) and team members.
- Connect with a token you were given, using the SDK snippet on the database row.

## What you cannot do

<div class="boundaries">
    <h3>Outside the role</h3>
    <ul>
        <li>Create databases, groups, teams or tokens.</li>
        <li>Archive or delete anything.</li>
        <li>Grant access to another user.</li>
        <li>Invite, update or remove team members.</li>
        <li>Reach the Tokens section — it is not part of the Member surface.</li>
    </ul>
</div>

<div class="callout callout--note">
    <span class="callout__title">If Studio returns nothing</span>
    <p>A blank Studio usually means the database has no tables yet, not that access is broken. If the table list itself fails to load, the sqld endpoint is unreachable from your browser — see the <a href="/user-manual/guides/studio/">Studio troubleshooting section</a>.</p>
</div>

## Related

- [Database Studio](/user-manual/guides/studio/)
- [Roles at a glance](/user-manual/roles/)
