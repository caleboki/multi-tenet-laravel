# HTTP Route Contract

**Feature**: [spec.md](../spec.md) | **Data model**: [data-model.md](../data-model.md)

Every route is a server-rendered Blade page or a form POST (R4). Each state-changing form
includes `@csrf`. Validation failures redirect back with errors and old input. Tests assert a 302
with session errors.

**Middleware key**:

| Key | Meaning |
|---|---|
| `guest` | Signed out only |
| `auth` | Signed in |
| `verified` | Email verified |
| `member` | `EnsureActiveMembership` |
| `admin` | Policy check on the current membership: Administrator |
| `operator` | `can:operate-platform` |

**Response codes**:
- **404**: not a member of the organization, or the record belongs to another organization
  (FR-004).
- **403**: an active member of a suspended organization (suspended page), or a volunteer on an
  admin-only route ("access denied" page with a link to `orgs.show`).
- **429**: rate limit hit (R14).

## Authentication (Fortify, R3)

| Method | URI | Name | Middleware | Notes |
|---|---|---|---|---|
| GET/POST | `/login` | `login` | guest, `throttle:login` | Email is lowercased. Redirects to `dashboard`. |
| POST | `/logout` | `logout` | auth | |
| GET/POST | `/forgot-password` | `password.request` / `password.email` | guest | Always shows "If an account exists, we've emailed a link" (no account enumeration). |
| GET | `/reset-password/{token}` | `password.reset` | guest | |
| POST | `/reset-password` | `password.update` | guest | |
| GET | `/email/verify` | `verification.notice` | auth | |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | auth, signed | Fires `Verified`, which releases waiting notifications (R9). |
| POST | `/email/verification-notification` | `verification.send` | auth, `throttle:6,1` | |
| PUT | `/user/profile-information` | `user-profile-information.update` | auth | Fields: `name`, `phone`. The email field is ignored (FR-043). |
| PUT | `/user/password` | `user-password.update` | auth | Fields: `current_password`, `password`, `password_confirmation` |

Fortify's own registration routes are **disabled**.

## Account-creating entry points

All three entry points take the same **account fields** when the person is signed out and new:
`name`, `email`, `password`, `password_confirmation`, `phone` (optional) and
`adult_confirmation` (must be accepted, FR-045). Signed-in users skip the account fields.

| Method | URI | Name | Middleware | Behaviour |
|---|---|---|---|---|
| GET | `/organizations/request` | `organization-requests.create` | — | Organization request form, plus account fields if signed out. |
| POST | `/organizations/request` | `organization-requests.store` | `throttle:public-forms` | Fields: `organization_name`, `contact_email` and account fields. Creates the organization as `pending` and signs a new user in. Redirects to `dashboard`, which shows "awaiting approval" (or "verify your email" first). |
| GET | `/join/{signupToken}` | `join.show` | — | Shows the organization name. Unknown or regenerated token → "This link is no longer valid". Organization not active, or self sign-up off → "not accepting sign-ups". |
| POST | `/join/{signupToken}` | `join.store` | `throttle:public-forms` | Creates a `pending` membership. Already active, pending or inactive → message, and no change (edge cases). `left` → back to `pending`. |
| GET | `/invitations/{token}` | `invitations.show` | — | Expired or unknown token → "ask your administrator". If the email has an account and the visitor is signed out → sign-in prompt that returns here. If signed in as a different email → "This invitation is for another email address". |
| POST | `/invitations/{token}` | `invitations.accept` | `throttle:public-forms` | New email: account fields, with name pre-filled and the email fixed. The account is created already verified, because the link proves the address. Existing account: must be signed in as that email. Creates or reactivates the membership as `active`, deletes the invitation, and redirects to `orgs.show`. |

## Signed-in, outside an organization

| Method | URI | Name | Middleware | Behaviour |
|---|---|---|---|---|
| GET | `/dashboard` | `dashboard` | auth | Where Fortify sends people after sign-in (`home`). Redirects to the last organization if it's still accessible, or to the only active one. Otherwise shows a chooser: active organizations, suspended ones (not clickable), pending invitations, join requests and organization requests, and the "verify your email" banner (FR-021, FR-022, edge cases). |
| GET | `/profile` | `profile.edit` | auth | Name, phone and password forms posting to the Fortify routes above. |

The organization switcher appears in the layout header on every authenticated page. It lists
active memberships in active organizations and links to `orgs.show` (FR-021, FR-023; switching
takes 2 actions, SC-006).

## Organization area

These routes use the prefix `/orgs/{organization:slug}`, name prefix `orgs.`, middleware
`auth, verified, member`, and `scopeBindings()`. `member` updates `users.last_organization_id`.

| Method | URI (after prefix) | Name | Access | Behaviour |
|---|---|---|---|---|
| GET | `/` | `orgs.show` | member | Organization home. Volunteers see the organization name, their role and a leave option. Administrators also see counts and links (FR-016). |
| DELETE | `/membership` | `orgs.membership.destroy` | member | Leave. Sets status to `left`. The last administrator is refused (FR-018, FR-024). |
| GET | `/members` | `orgs.members.index` | admin | Roster. Query parameters: `q`, `role`, `status`, `page` (FR-035, FR-036, FR-039). |
| GET | `/members/export` | `orgs.members.export` | admin | CSV download using the same filters ([csv-formats.md](csv-formats.md), FR-051). |
| GET | `/members/{membership}` | `orgs.members.show` | admin | Member details, read-only for personal fields (FR-037). |
| PATCH | `/members/{membership}` | `orgs.members.update` | admin | Fields: `role` and/or `status` (`active` or `inactive` only). Goes through `ChangeMembership`. The last administrator is refused (FR-017, FR-018, FR-038). |
| GET | `/join-requests` | `orgs.join-requests.index` | admin | Verified pending requests, paginated (FR-027). |
| POST | `/join-requests/{membership}/approve` | `orgs.join-requests.approve` | admin | Sets `active` and `joined_at`, then notifies (FR-028). |
| DELETE | `/join-requests/{membership}` | `orgs.join-requests.destroy` | admin | Decline: deletes the membership row, then notifies (FR-028). |
| GET | `/invitations/create` | `orgs.invitations.create` | admin | |
| POST | `/invitations` | `orgs.invitations.store` | admin | Fields: `name`, `email`, `role`. Duplicate rule (FR-034) → validation error on `email` pointing to the roster entry. |
| POST | `/invitations/{invitation}/resend` | `orgs.invitations.resend` | admin | New token and expiry, email re-sent (FR-033). |
| DELETE | `/invitations/{invitation}` | `orgs.invitations.destroy` | admin | Cancel (FR-033). |
| GET | `/imports/create` | `orgs.imports.create` | admin | Upload form and link to the sample file. |
| GET | `/imports/sample` | `orgs.imports.sample` | admin | Sample CSV download (FR-050). |
| POST | `/imports` | `orgs.imports.store` | admin, `throttle:roster-import` | Field: `file`. Whole-file rejection → validation error. Otherwise redirects to `orgs.imports.create` with the flashed report (FR-046 to FR-049). |
| GET | `/settings` | `orgs.settings.edit` | admin | Name, contact email, self sign-up toggle, and the sign-up link with a copy button. |
| PATCH | `/settings` | `orgs.settings.update` | admin | Fields: `name`, `contact_email`, `self_signup_enabled` (FR-008, FR-029). |
| POST | `/settings/signup-link` | `orgs.signup-link.store` | admin | Regenerates `signup_token`. The old link stops working immediately (FR-029). |

**Isolation contract (SC-001)**: For every route named `orgs.*`, a user who isn't an active
member of `{organization}` gets 404. A `{membership}` or `{invitation}` from another
organization gets 404 even for an administrator of `{organization}`. `TenantIsolationTest`
enforces this by listing every `orgs.*` route (R15).

## Platform operator area

These routes use the prefix `/operator`, name prefix `operator.`, and middleware
`auth, verified, operator`. Non-operators get 403.

| Method | URI (after prefix) | Name | Behaviour |
|---|---|---|---|
| GET | `/organizations` | `operator.organizations.index` | Query parameter `status`, default `pending`. Pending organizations are listed only if the requester has verified their email (R9). Columns: name, status, contact email, requester, created, active members (FR-011). |
| GET | `/organizations/{organization:slug}` | `operator.organizations.show` | Organization-level details only, with no roster (FR-014). |
| POST | `/organizations/{organization:slug}/approve` | `operator.organizations.approve` | Pending only. Otherwise 409, with "already decided". |
| POST | `/organizations/{organization:slug}/reject` | `operator.organizations.reject` | Field: `reason`. Pending only. |
| POST | `/organizations/{organization:slug}/suspend` | `operator.organizations.suspend` | Active only. |
| POST | `/organizations/{organization:slug}/reinstate` | `operator.organizations.reinstate` | Suspended only. |

## Artisan commands

| Command | Behaviour |
|---|---|
| `./vendor/bin/sail artisan app:grant-operator {email}` | Sets `is_platform_operator=true` for an existing user. Fails if no user has that email. |
| `./vendor/bin/sail artisan app:revoke-operator {email}` | Sets it back to `false`. |
