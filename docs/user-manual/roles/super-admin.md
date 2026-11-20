---
title: Super Admin
description: The platform owner's guide — users, teams, databases, grants and tokens across the whole installation.
section: Role guides
eyebrow: Role guide
---

<span class="role-badge">Super Admin</span>

The Super Admin is the only role that exists **above teams**. Everything the other roles can do inside their scope, a Super Admin can do everywhere — plus the platform-level actions nobody else can perform.

## What you see

The sidebar contains every section, including **User Management**:

- Dashboard
- Databases
- Tokens
- Groups
- Teams
- **User Management** <span class="role-badge">Super Admin only</span>

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/dashboard.png" alt="Super Admin dashboard with all databases and usage metrics">
    <figcaption>The Super Admin dashboard aggregates every database in the installation, not just one team.</figcaption>
</figure>

## Story: bring a team from zero to a working database

### 1. Create the team

Open **Teams** → **New Team**, give it a name and a description. A team is the container for groups, databases, tokens and invitations.

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/teams.png" alt="Teams page with the Acme Engineering team and its member avatars">
    <figcaption>A team row shows members, groups, databases and recent activity. The overflow menu holds update and archive actions.</figcaption>
</figure>

### 2. Create the people

Open **User Management** → **Add User**. For each person you set:

- full name, username and email;
- a password (or let them reset it);
- a **global role** — this is the role that decides which sections and actions exist for them;
- **team membership**, with a permission level per team.

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/users.png" alt="User management table with roles and team memberships">
    <figcaption>Every user, their global role, and the teams they belong to. Archiving a user is a soft delete — their history stays intact.</figcaption>
</figure>

<div class="callout callout--note">
    <span class="callout__title">Role vs team level</span>
    <p>The global role is what the policies read. The team permission level is the label shown inside the team context. Keep them aligned; a person with a Member role does not gain Manager abilities by being labelled Team Manager on one team.</p>
</div>

### 3. Build the structure

Create a **group** first, then a **database** inside it. Groups let several databases share one credential; a database on its own is perfectly valid too.

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/groups.png" alt="Groups page with analytics selected and its databases">
    <figcaption>Groups are per team. Selecting one shows the databases attached to it and the token state of each.</figcaption>
</figure>

### 4. Grant access

A Maintainer or Member will not see a database until it is granted. Open **Databases**, find the row, and use the grant action to attach a user. The **Granted Users** column shows the current count.

### 5. Issue credentials

Open **Tokens** → **Generate New Token**, pick the database and the user who will own the credential, set an expiry (0 means no expiry), and copy the pair immediately.

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/tokens-reveal.png" alt="One-time token reveal dialog with full-access and read-only tokens blurred">
    <figcaption>The one-time reveal. Both values are shown here and never again — only SHA-256 hashes are stored. Values are blurred in this screenshot.</figcaption>
</figure>

## What only you can do

- Create, update, archive and restore **teams**.
- Create, edit and archive **users**, and assign global roles.
- **Archive** a database, restore it, or force-delete it.
- **Grant** a database to a user.
- Restore or force-delete soft-deleted records.

## Boundaries worth respecting

<div class="boundaries">
    <h3>Not recoverable, by design</h3>
    <ul>
        <li>Database tokens are hashed at rest. Losing the plaintext means minting a new pair — there is no recovery screen.</li>
        <li>Archiving forks the namespace to <code>&lt;name&gt;-archived</code> and removes the live one; <strong>Delete permanently</strong> destroys that archived namespace too. Neither is a substitute for a backup of the sqld volume.</li>
        <li>Activity logs record metadata, never raw tokens. Do not expect to reconstruct a credential from the audit trail.</li>
    </ul>
</div>

<div class="callout callout--danger">
    <span class="callout__title">Production hygiene</span>
    <p>Keep <code>APP_DEBUG</code> off, keep registration disabled unless you deliberately run an open instance, and delete <code>storage/app/bootstrap-credentials.txt</code> after the first login. Those three settings are the difference between a control plane and an open door.</p>
</div>

## Related

- [Permission matrix]({{ site.baseurl }}/user-manual/reference/permissions/) — every permission string.
- [Teams &amp; members]({{ site.baseurl }}/user-manual/guides/teams/) — invitations and membership.
- [Tokens]({{ site.baseurl }}/user-manual/guides/tokens/) — lifecycle and revocation.
