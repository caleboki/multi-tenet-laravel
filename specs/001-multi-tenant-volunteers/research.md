# Research: Multi-Tenant Organizations & Volunteers

**Feature**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Date**: 2026-10-05

Each entry records a decision, why it was made, and what else was considered. Versions were
checked against the installed packages (Laravel 13.34, PHPUnit 12.5, Sail 1.68) and Laravel 13
documentation.

---

## R1. Tenancy model

**Decision**: Single PostgreSQL database. Every organization-owned table has an
`organization_id` foreign key. No tenancy package.

**Rationale**: The spec needs one account to belong to many organizations (FR-019) and to switch
between them (FR-021). Database-per-tenant or schema-per-tenant designs make cross-organization
membership awkward, because the user, membership and organization would live in different
databases. At the target scale (500 organizations, 50,000 memberships; SC-008), indexed
`organization_id` columns are sufficient. Constitution IV requires a dependency to justify a
capability the framework lacks, and Eloquent relationships plus scoped route bindings already
cover single-database tenancy.

**Alternatives considered**:
- `stancl/tenancy` or `spatie/laravel-multitenancy`: built around a single current tenant per
  request, with optional separate databases. They add a dependency and concepts the spec doesn't
  need.
- A global Eloquent scope that reads a "current organization" from the session: hidden
  filtering makes it easy to forget the scope in jobs and operator screens, and it conflicts with
  operators who must see all organizations. Rejected in favour of explicit relationship queries.

## R2. Identifying the current organization

**Decision**: The organization is part of the URL: `/orgs/{organization:slug}/...`. Middleware
(`EnsureActiveMembership`) asks `OrganizationPolicy::view` whether the signed-in user may enter
the organization, and refuses the request unless both the membership and the organization are
active. The rule lives in the policy, not the middleware (Constitution I). Child records
are bound with `Route::scopeBindings()`, so a record from another organization is resolved
through `$organization->memberships()` and returns 404.

**Rationale**: A URL-carried tenant works in several browser tabs at once, can be bookmarked,
and makes every test explicit about which organization it targets. Scoped bindings are a
documented Laravel 13 feature and give the "not found" response FR-004 requires without extra
code. The last organization used is stored on `users.last_organization_id` to meet FR-022.

**Alternatives considered**:
- Session-stored current organization: invisible in the URL, so switching in one tab silently
  changes another tab's context. Rejected.
- Subdomain per organization: needs wildcard DNS and TLS. Custom domains and branding are out of
  scope.

**Response codes**: Non-members get 404 for every organization route (FR-004). Active members of
a *suspended* organization get a "this organization is suspended" page (403). They are members,
so this reveals nothing new.

## R3. Authentication stack

**Decision**: `laravel/fortify ^1.40` (approved by the user on 2026-10-05). Enabled features:
`resetPasswords`, `emailVerification`, `updateProfileInformation`, `updatePasswords`.
**Registration is disabled**: the three account-creation entry points (organization request,
sign-up link, invitation) are separate controllers that call one `CreateAccount` action. Views
are Blade templates registered through `Fortify::loginView()` and similar methods.
`lowercase_usernames` is set to `true`.

**Rationale**: Fortify v1.40.0 requires `illuminate/support ^11.0|^12.0|^13.0`, so it supports
Laravel 13 (checked with `./vendor/bin/sail composer show -a laravel/fortify v1.40.0`). It provides login
throttling, password reset, signed email verification and password updates. These are the
security-sensitive flows we would otherwise write and maintain ourselves. Fortify has no UI of
its own, which fits the Blade-only decision.

**Alternatives considered**: Building the controllers ourselves on framework primitives (more
security-critical code to own). Starter kits (they scaffold a new application rather than add
auth to an existing one, and bring Livewire or Inertia).

## R4. Frontend

**Decision**: Server-rendered Blade with Tailwind CSS 4 and plain HTML forms. Roster search,
filters and pagination use GET query strings. No JavaScript framework.

**Rationale**: Approved by the user on 2026-10-05. It matches the existing skeleton and adds no
dependencies. Every interaction can be tested with HTTP feature tests.

## R5. Representing "invited" members

**Decision**: Invitations live in their own `invitations` table and do **not** create a User
or Membership until they are accepted. The roster is a read model that combines memberships
(joined to users) with open invitations: `UNION ALL` wrapped in a subquery
(`DB::query()->fromSub(...)`), then filtered, sorted and paginated as one list.

**Rationale**:
- FR-045 says an account must not be created until the person confirms they are 18 or older.
  Creating placeholder users at invitation time would contradict it.
- Imported or invited emails never become accounts unless the person accepts.
- Laravel's paginator supports union queries (it wraps the count in a subquery). Filtering on the
  wrapped subquery keeps search, status filters, sorting and CSV export (FR-051) on one code
  path.

**Alternatives considered**:
- Placeholder users with null passwords: conflicts with FR-045 and complicates self sign-up for
  the same email.
- Memberships with a nullable `user_id` plus invitation name and email columns: two sources of
  truth for name and email, and conditional uniqueness rules.

## R6. Uniqueness and normalisation

**Decisions**:
- **Emails** are trimmed and lowercased before validation, both in Form Requests
  (`prepareForValidation`) and in Fortify (`lowercase_usernames`). `users.email` keeps its plain
  unique index.
- **Organization names** must be unique without regard to case among non-rejected organizations
  (FR-007). This is enforced by a PostgreSQL partial expression index:
  `CREATE UNIQUE INDEX organizations_name_lower_unique ON organizations (lower(name)) WHERE
  status <> 'rejected'`. A validation rule gives a friendly error.
- **Invitations**: there is no audit history (Clarifications), so accepted and cancelled
  invitations are deleted. That allows a plain `unique(organization_id, email)`. Resending
  updates the token and expiry on the same row.
- **Memberships**: `unique(organization_id, user_id)`. A declined join request deletes the row
  (FR-028). Leaving sets the status to `left`, and rejoining reuses the row.

**Rationale**: Laravel 13's schema builder has no partial-index API. The only partial index
methods are `rawIndex`, which isn't unique, and `nullsNotDistinct`. Constitution I allows raw SQL
when the builder cannot express the query, provided it is documented in a PHPDoc block. The
database constraint closes the race between validation and insert. Deleting finished invitations
avoids a second partial index.

**Alternatives considered**: A nullable `name_key` column cleared on rejection, which works
without raw SQL but encodes status logic in data. A `citext` column type, which needs a
PostgreSQL extension.

## R7. Organization URL slug

**Decision**: The slug is generated once from the name at request time (`Str::slug`, with a
numeric suffix if needed) and never changes, even when the name is updated (FR-008). It is unique
across all organizations, including rejected ones.

**Rationale**: Bookmarks and links in emails keep working after a rename. It's simpler than
keeping slug-history redirects.

## R8. Keeping at least one administrator

**Decision**: Every role or status change that could remove an administrator goes through one
action (`ChangeMembership`). It runs in a transaction, locks the organization row with
`lockForUpdate()`, counts the other active administrators, and refuses the change if none would
remain (FR-018).

**Rationale**: Without the lock, two administrators demoting each other at the same moment could
both pass the check and leave the organization with none. The row lock serialises those changes
for one organization only.

## R9. Email verification gate for requests

**Decision**: Join requests (`memberships.status = pending`) and organization requests
(`organizations.status = pending`) are saved as soon as they are submitted. They appear in
administrator and operator queues, and trigger notifications, only once the requester's email is
verified. A listener on `Illuminate\Auth\Events\Verified` sends the notifications that were
waiting on verification. If the requester is already verified, they are sent straight away.

**Rationale**: Meets FR-041 without a separate "unsubmitted request" store. The organization
name is reserved from the moment of submission, so two requests can't race for it.

**Known gap (accepted)**: An organization request whose requester never verifies keeps its name
reserved. This is acceptable for v1. A scheduled clean-up can be added later if it becomes a
problem.

## R10. Roster import

**Decision**: The upload is parsed and validated **during the request**, without a background
import job. The file is validated (`mimes:csv,txt`, `max:1024` KB) and read with `SplFileObject`
in CSV mode. Rules:
- The header row is required. Column names are matched case-insensitively, with surrounding
  spaces and a UTF-8 BOM removed.
- More than 1,000 data rows rejects the whole file (FR-047).
- Rows are validated with the same rules as a single invitation. Existing roster emails are loaded
  in **one** query to apply FR-034.
- Invitations are inserted in a single transaction, and invitation emails are queued after
  commit.
- The report (counts plus skipped rows with row numbers and reasons) is flashed to the session
  and shown on redirect (FR-049).

**Rationale**: Parsing and inserting 1,000 rows takes a few seconds, well within SC-010
(2 minutes). Queuing the emails keeps the request fast. A job-based import would need stored
import state and a polling screen, which the no-history decision doesn't otherwise require.

**Safety**: Uploads are never stored on disk past the request. Skip reasons never reveal
membership in other organizations (FR-049): an email that belongs only to another organization
is simply invited.

## R11. Roster export

**Decision**: `response()->streamDownload()` writes rows with `fputcsv`, using the roster read
model (R5) with the current filters and `lazy()` iteration. Any cell beginning with `=`, `+`,
`-`, `@`, a tab or a carriage return is prefixed with a single quote to prevent spreadsheet
formula injection.

**Rationale**: Streaming keeps memory flat for large rosters. Names and phone numbers are
user-supplied, so escaping formula characters is a necessary defence for anyone opening the file
in Excel or Sheets.

## R12. Notifications and queues

**Decision**: Every email in FR-044 is a queued notification (`ShouldQueue`) dispatched with
`afterCommit()`, on the existing `database` queue connection. Invitations to email addresses
without an account use on-demand notifications (`Notification::route('mail', $email)`). Email
verification and password reset use the framework's built-in notifications.

**Rationale**: Laravel's guidance is to queue emails and dispatch them after commit so they
never refer to rolled-back data. The `database` queue and `jobs` table already exist.

**Development**: `.env` has `MAIL_MAILER=log`, so emails appear in `storage/logs/laravel.log`.
Mailpit can be added with `./vendor/bin/sail artisan sail:add mailpit` if a mail UI is wanted.
That's optional, so it isn't part of this plan.

## R13. Platform operators

**Decision**: A boolean `users.is_platform_operator`, checked by a `Gate` named
`operate-platform`. Operators are granted and revoked with Artisan commands
(`app:grant-operator {email}` and `app:revoke-operator {email}`), matching the assumption that
operators are set up at deployment. Operator screens show only organization-level data, with
member counts from `withCount`. There is no operator route into rosters (FR-014).

**Alternatives considered**: A roles table or a permissions package. Over-built for a single
platform-level flag.

## R14. Rate limiting

**Decision**:
- **Fortify's `login` limiter**: 5 per minute by email and IP.
- **New limiter `public-forms`**: 10 per minute by IP, for organization requests, sign-up link
  submissions and invitation acceptance.
- **New limiter `roster-import`**: 5 per minute by user.
- **Verification resend**: throttled at 6 per minute (the Fortify default).

**Rationale**: Covers the spec's assumption about protecting public forms against automated
abuse, using framework rate limiting only.

## R15. Testing approach

**Decision**:
- **Feature tests**: one PHPUnit feature test class per controller or flow, grouped by user
  story, using `RefreshDatabase` against the Sail PostgreSQL `testing` database. Each test covers
  the happy path, validation failures, and the guest / non-member / volunteer / administrator
  authorization boundaries, as Constitution II requires.
- **Isolation sweep**: a dedicated `TenantIsolationTest` lists every route named `orgs.*` and
  asserts that a member of Organization A gets 404 for Organization B's URLs. Routes added later
  are covered automatically (SC-001).
- **Unit tests**: CSV header and row parsing, and CSV cell escaping.
- **Fakes**: `Notification::fake()` and `Queue::fake()`; no real email is sent.

**Rationale**: Constitution II requires tests first, against PostgreSQL, with fakes for external
services. The route sweep turns SC-001 into a test that keeps running as new routes are added.
