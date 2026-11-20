---
title: Teams &amp; members
description: Teams, invitations, membership levels and the team switcher — how people end up with access to a database.
section: Guides
eyebrow: People
---

A **team** is the container for groups, databases, tokens and invitations. Almost every object in MylibSQLAdmin belongs to exactly one team, and policies compare that team with your membership before they allow anything.

<figure class="screenshot">
    <img src="/images/teams.png" alt="Teams page with member avatars, groups, databases and recent activity">
    <figcaption>The team dashboard: members, groups, databases and recent activity in one row.</figcaption>
</figure>

## Create a team

**Teams** → **New Team**. Give it a name and a description. Only a Super Admin can create or delete a team; a Team Manager can update the team they belong to.

The description appears in the team switcher, which is the control that scopes every list in the app to the current team.

## Add members

Open the team's **Manage members** action.

1. Enter the invitee's email address.
2. Choose their **team permission level**.
3. Send the invitation.

The invitation is bound to that email address. If someone else opens the link, acceptance fails — the invitee must register or sign in with the invited address.

<figure class="screenshot">
    <img src="/images/team-manager-groups.png" alt="Groups page as a team manager sees it">
    <figcaption>Team structure — groups and their databases — is what a Team Manager is accountable for.</figcaption>
</figure>

Pending invitations are visible from the team row. From there you can resend an invitation, revoke it, or remove an existing member.

## Team permission levels

Each membership carries a level. It is a label used inside the team context and for the invitation flow:

| Level | Typical use |
|---|---|
| Super Admin | Platform owners who belong to the team |
| Team Manager | The person accountable for the team's structure |
| Database Maintainer | The person on call for the team's databases |
| Member | Everyone who only needs to query |

<div class="callout callout--warn">
    <span class="callout__title">The global role is what policies read</span>
    <p>The team level is not a second grant of permissions. A user whose global role is Member does not gain Manager abilities because their team level says Team Manager. Assign the global role deliberately from <strong>User Management</strong>.</p>
</div>

## The team switcher

The switcher in the sidebar changes the **current team**. Every scoped list — databases, tokens, groups — follows it. If a database "disappeared", check the switcher before checking permissions: you may simply be looking at another team.

## Remove someone

Removing a member ends their access to the team's objects immediately. It does not delete their user account; they keep their global role and any other team memberships. To remove them from the platform entirely, archive the user from **User Management** (Super Admin).

<div class="callout callout--note">
    <span class="callout__title">Deactivated means deactivated</span>
    <p>An inactive user loses access immediately, including any session in progress. Removing a team member is narrower: it only ends that team's scope.</p>
</div>

## Related

- [Roles at a glance](/user-manual/roles/)
- [Databases &amp; groups](/user-manual/guides/databases/)
- [Permission matrix](/user-manual/reference/permissions/)
