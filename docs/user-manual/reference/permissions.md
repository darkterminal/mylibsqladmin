---
title: Permission matrix
description: Every permission string the seeder assigns, and which role holds it.
section: Reference
eyebrow: Reference
toc: true
---

This page is the literal mapping produced by `RoleUserSeeder`. Permission strings are what the policies check; the friendly names in the role guides are just a reading aid.

## How to read it

- **`manage-*`** is a superset. A policy that checks `manage-groups` accepts it in place of any `create-` / `update-` / `delete-groups` check.
- A permission answers *what kind of action*. It does not answer *which object* — policies add the team and owner checks on top.
- `update-databases` is assigned to Super Admin but the corresponding feature is not implemented; it exists for completeness.

## Databases

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-databases` | ✓ | — | — | — |
| `view-databases` | ✓ | ✓ | ✓ | ✓ |
| `create-databases` | ✓ | ✓ | — | — |
| `update-databases` | ✓ | — | — | — |
| `delete-databases` | ✓ | — | — | — |

## Database tokens

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-database-tokens` | ✓ | — | ✓ | — |
| `view-database-tokens` | ✓ | ✓ | ✓ | ✓ |
| `create-database-tokens` | ✓ | — | ✓ | — |
| `update-database-tokens` | ✓ | — | ✓ | — |
| `delete-database-tokens` | ✓ | — | ✓ | — |

## Teams

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-teams` | ✓ | — | — | — |
| `view-teams` | ✓ | ✓ | ✓ | ✓ |
| `create-teams` | ✓ | — | — | — |
| `update-teams` | ✓ | ✓ | — | — |
| `delete-teams` | ✓ | — | — | — |

## Groups

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-groups` | ✓ | ✓ | — | — |
| `view-groups` | ✓ | ✓ | ✓ | ✓ |
| `create-groups` | ✓ | ✓ | — | — |
| `update-groups` | ✓ | ✓ | — | — |
| `delete-groups` | ✓ | ✓ | — | — |

## Group tokens

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-group-tokens` | ✓ | ✓ | — | — |
| `view-group-tokens` | ✓ | ✓ | ✓ | ✓ |
| `create-group-tokens` | ✓ | ✓ | — | — |
| `update-group-tokens` | ✓ | ✓ | — | — |
| `delete-group-tokens` | ✓ | ✓ | — | — |

## Team members

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-team-members` | ✓ | ✓ | — | — |
| `view-team-members` | ✓ | ✓ | ✓ | ✓ |
| `create-team-members` | ✓ | ✓ | — | — |
| `update-team-members` | ✓ | ✓ | — | — |
| `delete-team-members` | ✓ | ✓ | — | — |

## Users

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-users` | ✓ | — | — | — |
| `view-users` | ✓ | ✓ | — | — |
| `create-users` | ✓ | — | — | — |
| `update-users` | ✓ | — | — | — |
| `delete-users` | ✓ | — | — | — |

## Roles

| Permission | Super Admin | Team Manager | Database Maintainer | Member |
|---|:---:|:---:|:---:|:---:|
| `manage-roles` | ✓ | — | — | — |
| `view-roles` | ✓ | ✓ | — | — |
| `create-roles` | ✓ | — | — | — |
| `update-roles` | ✓ | — | — | — |
| `delete-roles` | ✓ | — | — | — |

## Scoping rules that sit on top

A permission alone is never sufficient. The policies add:

| Policy | Extra condition |
|---|---|
| `TeamPolicy` | Update/delete requires Super Admin, or membership in the team being changed. |
| `GroupDatabasePolicy` | Update/delete requires Super Admin, or membership in the group's team. |
| `UserDatabasePolicy` | Update/delete requires Super Admin or ownership of the database, **and** the matching permission. Restore/force-delete requires Super Admin. |
| `UserDatabaseTokenPolicy` | Operations follow the same permission checks; restore/force-delete is Super Admin only. |
| `GroupDatabaseTokenPolicy` | Create/update/delete requires the matching group-token permission; restore/force-delete is Super Admin only. |
| `UserPolicy` | Restore/force-delete requires Super Admin. |
| `RolePolicy` | Role administration is Super Admin territory. |

And two list-level rules that are not permissions at all:

- A non-Super-Admin, non-Team-Manager only sees databases with a row in `granted_user_databases`.
- Every scoped list is filtered by the **current team** from the team switcher.

## Related

- [Roles at a glance]({{ site.baseurl }}/user-manual/roles/)
- [Teams &amp; members]({{ site.baseurl }}/user-manual/guides/teams/)
