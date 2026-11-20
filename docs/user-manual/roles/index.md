---
title: Roles at a glance
description: The four MylibSQLAdmin roles, what each one is responsible for, and the permission matrix that separates them.
section: Role guides
eyebrow: Access control
---

MylibSQLAdmin ships four roles. They are not a hierarchy of seniority — they describe **where a person sits in the system**: above teams, inside a team, on a database, or on the receiving end of access.

<div class="card-grid">
    <a class="card" href="{{ site.baseurl }}/user-manual/roles/super-admin/">
        <span class="card__title">Super Admin</span>
        <span class="card__desc">Platform owner. Every team, every database, every user, every token.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/roles/team-manager/">
        <span class="card__title">Team Manager</span>
        <span class="card__desc">Runs one team: groups, group credentials, databases and membership.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/roles/database-maintainer/">
        <span class="card__title">Database Maintainer</span>
        <span class="card__desc">Owns the databases granted to them and the tokens that unlock them.</span>
    </a>
    <a class="card" href="{{ site.baseurl }}/user-manual/roles/member/">
        <span class="card__title">Member</span>
        <span class="card__desc">Uses granted databases through Studio or a token. Read-only by default.</span>
    </a>
</div>

## The matrix

This is the short version. The [full permission matrix]({{ site.baseurl }}/user-manual/reference/permissions/) lists every permission string the seeder assigns.

<div class="table-scroll">
<table>
    <thead>
        <tr>
            <th>Capability</th>
            <th>Super Admin</th>
            <th>Team Manager</th>
            <th>Database Maintainer</th>
            <th>Member</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>View databases</td><td>All</td><td>Team</td><td>Granted only</td><td>Granted only</td></tr>
        <tr><td>Create databases</td><td>Yes</td><td>Yes, in own team</td><td>—</td><td>—</td></tr>
        <tr><td>Archive / delete databases</td><td>Yes</td><td>—</td><td>—</td><td>—</td></tr>
        <tr><td>Grant a database to a user</td><td>Yes</td><td>—</td><td>—</td><td>—</td></tr>
        <tr><td>Create / delete groups</td><td>Yes</td><td>Yes, in own team</td><td>—</td><td>—</td></tr>
        <tr><td>View groups</td><td>Yes</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
        <tr><td>Mint database tokens</td><td>Yes</td><td>—</td><td>Yes</td><td>—</td></tr>
        <tr><td>View database tokens</td><td>Yes</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
        <tr><td>Mint / revoke group tokens</td><td>Yes</td><td>Yes</td><td>—</td><td>—</td></tr>
        <tr><td>Invite / remove team members</td><td>Yes</td><td>Yes, own team</td><td>—</td><td>—</td></tr>
        <tr><td>View team members</td><td>Yes</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
        <tr><td>Update team details</td><td>Yes</td><td>Yes, own team</td><td>—</td><td>—</td></tr>
        <tr><td>Create / delete teams</td><td>Yes</td><td>—</td><td>—</td><td>—</td></tr>
        <tr><td>Create / edit / archive users</td><td>Yes</td><td>—</td><td>—</td><td>—</td></tr>
        <tr><td>Open Database Studio</td><td>Yes</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
    </tbody>
</table>
</div>

<figure class="screenshot">
    <img src="{{ site.baseurl }}/images/users.png" alt="User management table listing each user with role, teams and last activity">
    <figcaption>User Management is a Super Admin area. Each row shows the global role, the teams the user belongs to, and when they were last active.</figcaption>
</figure>

## Role, membership, grant

A person's effective access is the intersection of three things:

- **Role** answers *what kind of action* is allowed (create a group, mint a token, archive a user).
- **Team membership** answers *where* it is allowed. Policies compare the object's `team_id` with the user's membership row.
- **Grant** answers *which database* a Maintainer or Member can see. Without a grant, a database does not appear in their list even if their role can view databases.

<div class="callout callout--warn">
    <span class="callout__title">Two things that surprise people</span>
    <p>A Team Manager cannot mint per-database tokens — that permission belongs to Super Admin and Database Maintainer. Team Managers issue <strong>group tokens</strong> instead, which is why the Groups screen is their credential workspace.</p>
    <p>A Member has no Tokens entry in the sidebar at all. They receive credentials from someone else and use them through Studio or the SDK.</p>
</div>

## Choosing a role

- Give **Super Admin** to as few people as possible. It is the only role that can create users, delete teams, and archive databases.
- Use **Team Manager** for the person accountable for a team's structure: who is in it, which groups exist, which credentials the team hands out.
- Use **Database Maintainer** for the person on call for specific databases. They can rotate credentials without being able to delete data.
- Use **Member** for everyone who only needs to query. Combine it with a grant, not with a broader role.
