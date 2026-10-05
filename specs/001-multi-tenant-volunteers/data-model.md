# Data Model: Multi-Tenant Organizations & Volunteers

**Feature**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

Database: PostgreSQL 18 (Sail). All timestamps are `timestamp` (UTC). Enum-backed columns are
`string` columns cast to PHP backed enums. All emails are stored trimmed and lowercased (R6).

## Entity overview

```text
User 1 ──< Membership >── 1 Organization 1 ──< Invitation
  │                              │
  └── requested ─────────────────┘ (organizations.requested_by_id)
  └── last_organization_id ──> Organization (nullable)
```

---

## Organization

| Column | Type | Rules |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(120) | Required. Unique ignoring case among non-rejected organizations (FR-007). |
| `slug` | string(140), unique | Generated from the name at creation and never changed (R7). |
| `contact_email` | string(255) | Required, valid email (FR-009). |
| `status` | string → `OrganizationStatus` | Default `pending`. Indexed. |
| `rejection_reason` | text, nullable | Required when status is `rejected` (FR-012). |
| `requested_by_id` | FK → users | The requester, who becomes the first administrator on approval. |
| `signup_token` | string(64), nullable, unique | Set on approval and replaced on regeneration (FR-025, FR-029). |
| `self_signup_enabled` | boolean | Default `true` (FR-029). |
| `created_at`, `updated_at` | timestamps | `created_at` is the creation date shown to operators (FR-011). |

**Indexes**:
- `unique(slug)` and `unique(signup_token)`.
- `index(status)`.
- A **partial unique expression index** on `lower(name) WHERE status <> 'rejected'`, created
  with `DB::statement`. The schema builder has no partial-index API, so the raw SQL is
  documented in the migration's PHPDoc (R6).

**Relationships**: `memberships()` HasMany, `invitations()` HasMany, `requester()` BelongsTo
User.

### OrganizationStatus (`App\Enums\OrganizationStatus`)

| Case | Value | Meaning |
|---|---|---|
| `Pending` | `pending` | Awaiting operator review. No invitations or join requests (FR-010). |
| `Active` | `active` | Normal operation. |
| `Rejected` | `rejected` | Final. The name becomes available again. |
| `Suspended` | `suspended` | No member can access it until reinstated (FR-013). |

```text
pending ──approve──> active ──suspend──> suspended
   │                   ^                     │
   └──reject──> rejected   └────reinstate────┘
```

- **Approve**: in one transaction, sets `status=active`, generates `signup_token`, creates the
  requester's Membership (`Administrator`, `active`, `joined_at=now`), and notifies the
  requester.
- **Reject**: sets `status=rejected` and `rejection_reason`, then notifies the requester.
- **Suspend**: notifies the active administrators. **Reinstate** sends no email; the spec
  doesn't list one.

---

## User (existing table, extended)

| Column | Type | Rules |
|---|---|---|
| `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps | existing | `email` unique and lowercased. `name` required, max 255. |
| `phone` | string(32), nullable | Optional. Digits, spaces, `+ ( ) -` only. Max 32 characters. |
| `adult_confirmed_at` | timestamp, nullable | Set when the account is created. Required by every account-creation flow (FR-045). Nullable in the database only so the column can be added safely. |
| `is_platform_operator` | boolean | Default `false`. Set only by Artisan commands (R13). |
| `last_organization_id` | FK → organizations, nullable, `nullOnDelete` | Updated when the user opens an organization (FR-022). |

**Model**: implements `MustVerifyEmail`. `#[Fillable]` adds `phone` only.
`adult_confirmed_at`, `is_platform_operator` and `last_organization_id` are assigned explicitly
in code, never mass-assigned from a request.

**Relationships**: `memberships()` HasMany, `lastOrganization()` BelongsTo. Organizations are
reached through `memberships.organization`.

**Personal details** (name, phone, password) can only be changed by the user (FR-037, FR-043).
Administrators see them read-only. Changing the email address is out of scope.

---

## Membership

| Column | Type | Rules |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | FK → organizations, `cascadeOnDelete` | |
| `user_id` | FK → users, `cascadeOnDelete` | |
| `role` | string → `MembershipRole` | |
| `status` | string → `MembershipStatus` | |
| `requested_at` | timestamp, nullable | Set when a join request is made through the sign-up link. |
| `joined_at` | timestamp, nullable | Set when the status becomes `active` from `pending`, from `left`, or through invitation acceptance. Not changed by reactivation. |
| `created_at`, `updated_at` | timestamps | |

**Indexes**: `unique(organization_id, user_id)`, `index(organization_id, status)`,
`index(user_id)`. PostgreSQL does not index foreign keys automatically.

**Scopes**: `active()`, `administrators()`, `awaitingApproval()`. The last returns
`status=pending` where the user's email is verified (R9).

### MembershipRole (`App\Enums\MembershipRole`)

| Case | Value |
|---|---|
| `Administrator` | `administrator` |
| `Volunteer` | `volunteer` |

### MembershipStatus (`App\Enums\MembershipStatus`)

| Case | Value | Access to organization |
|---|---|---|
| `Pending` | `pending` | None. Shown as "Pending approval". |
| `Active` | `active` | Yes |
| `Inactive` | `inactive` | None. Only an administrator can reactivate. |
| `Left` | `left` | None. May rejoin through the sign-up link or an invitation. |

```text
(none) ──join via link──> pending ──approve──> active
                             │                  │  ^
                       decline (row deleted)    │  │ reactivate
                                                │  │
(none) ──accept invitation──> active   deactivate  inactive
                                                │
active ──leave──> left ──join via link──> pending
                   └────accept invitation──> active
```

**Invariant (FR-018)**: an active organization always has at least one membership with
`role=Administrator` and `status=active`. Every change to role or status, including leaving,
runs through `ChangeMembership`. That action locks the organization row (`lockForUpdate`) inside
a transaction and refuses the change if no other active administrator would remain (R8).

**Duplicate rule (FR-034)**: a new invitation or join request is refused if the person has a
membership in the organization with status `pending`, `active` or `inactive`, **or** an
invitation is open for that email. `left` doesn't block either.

---

## Invitation

| Column | Type | Rules |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | FK → organizations, `cascadeOnDelete` | |
| `invited_by_id` | FK → users | The administrator who issued or last resent it. |
| `name` | string(255) | Required. Pre-fills the setup form for new accounts. |
| `email` | string(255) | Required, valid, lowercased. |
| `role` | string → `MembershipRole` | Chosen by the administrator (FR-030). Imports always use `Volunteer` (FR-046). |
| `token_hash` | string(64), unique | SHA-256 of the emailed token. The plain token is never stored. |
| `expires_at` | timestamp | `now + 7 days` when issued or resent (FR-031). |
| `created_at`, `updated_at` | timestamps | |

**Indexes**: `unique(organization_id, email)`, `unique(token_hash)`.

**Lifecycle**:
- **Issue**: creates the row and queues the email.
- **Resend**: replaces `token_hash`, extends `expires_at`, and re-sends the email. The old link
  stops working (FR-033).
- **Cancel**: deletes the row.
- **Accept**: deletes the row and creates or reactivates the membership as `active`. If the
  person had left, their existing membership row is reused (FR-031, FR-032).
- **Accepting after `expires_at`** is refused ("ask your administrator for a new invitation").

There is no audit history (Clarifications), so finished invitations are not kept.

---

## Roster entry (read model, not a table)

The roster combines memberships (joined to users) with open invitations, using
`UNION ALL` → `fromSub` → filter → order → paginate (R5). It's built by
`App\Actions\Roster\BuildRosterQuery`, and the roster screen and CSV export (FR-051) both use it.

| Field | From a membership | From an invitation |
|---|---|---|
| `kind` | `membership` | `invitation` |
| `key` | membership id | invitation id |
| `name`, `email` | user's | invitation's |
| `phone` | user's | `null` |
| `role` | membership role | invitation role |
| `status` | membership status | `invited` |
| `joined_at` | membership `joined_at` | `null` |
| `listed_at` | `coalesce(joined_at, requested_at, created_at)` | `created_at` |

**Included rows**: every membership of the organization except unverified `pending` ones (R9),
plus every open invitation.

**Filters**:
- `q`: case-insensitive `whereLike` on name or email, with `%` and `_` escaped.
- `role`: `MembershipRole`.
- `status`: `RosterStatus`.

**Order**: `name asc`, then `kind`, then `key`, so pagination is stable. 25 per page.

### RosterStatus (`App\Enums\RosterStatus`)

This enum is used for display and filtering only, and is never stored.

| Case | Value | Label |
|---|---|---|
| `Invited` | `invited` | Invited |
| `Pending` | `pending` | Pending approval |
| `Active` | `active` | Active |
| `Inactive` | `inactive` | Inactive |
| `Left` | `left` | Left |

---

## Validation summary

| Input | Rules |
|---|---|
| Organization name | `required`, `string`, `max:120`, unique ignoring case among non-rejected organizations (custom rule plus database index). |
| Contact email | `required`, `email:rfc`, `max:255` |
| Account name | `required`, `string`, `max:255` |
| Account email | `required`, `email:rfc`, `max:255`, `unique:users,email` (new accounts only) |
| Password | `required`, `confirmed`, `Password::defaults()`: minimum 8, plus `uncompromised()` in production only, so tests make no network calls. |
| Phone | `nullable`, `string`, `max:32`, `regex:/^[0-9 +()\-]+$/` |
| Adult confirmation | `accepted` (FR-045) |
| Rejection reason | `required`, `string`, `max:1000` |
| Invitation role | `required`, `Rule::enum(MembershipRole::class)` |
| Import file | `required`, `file`, `mimes:csv,txt`, `max:1024` (KB). At most 1,000 data rows (FR-047). |
| Roster filters | `q`: `nullable`, `string`, `max:100`. `role`: `nullable`, enum. `status`: `nullable`, `Rule::enum(RosterStatus::class)`. |

## Volume and performance notes

- **Volume**: about 500 organizations, 50,000 memberships, and at most 1,000 invitations per
  import.
- **Roster queries**: always filtered by `organization_id` first, using the leading column of
  both unique indexes. At roughly 1,000 rows per organization, `ILIKE` search needs no trigram
  index (SC-007).
- **Eager loading**: switcher and dashboard queries load memberships with `organization`. Operator
  lists use `withCount(['memberships as active_members_count' => active])`.
