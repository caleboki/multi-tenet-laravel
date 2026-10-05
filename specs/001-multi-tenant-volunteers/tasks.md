---

description: "Task list for Multi-Tenant Organizations & Volunteers"
---

# Tasks: Multi-Tenant Organizations & Volunteers

**Input**: Design documents from `/specs/001-multi-tenant-volunteers/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: **Required.** Constitution II (Test-First Verification) is non-negotiable. Every test
task comes before the implementation tasks it covers and MUST fail before they start. Create
tests with `./vendor/bin/sail artisan make:test --phpunit {Area}/{Name}` (add `--unit` for unit
tests) and run them with `./vendor/bin/sail artisan test --compact {path}`.

**Organization**: Tasks are grouped by user story, so each story can be built, tested and demoed
on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US6)

## Conventions for every task

- **Sail**: Run every command with the `./vendor/bin/sail` prefix (Constitution V).
- **Generators**: Generate classes with `./vendor/bin/sail artisan make:* --no-interaction`
  (`make:model`, `make:migration`, `make:enum`, `make:controller`, `make:request`,
  `make:policy`, `make:notification`, `make:listener`, `make:middleware`, `make:rule`,
  `make:command`, `make:class`, `make:factory`, `make:seeder`, `make:test`).
- **PHP style**: Typed parameters and return types, constructor property promotion, curly braces
  on every control structure, TitleCase enum cases, PHPDoc array shapes (Constitution III).
- **Validation and authorization**: Validation lives in Form Requests. Admin-only routes
  authorize with `OrganizationPolicy::manageMembers`.
- **Notifications**: Queued (`ShouldQueue`) and sent with `->afterCommit()`
  (contracts/notifications.md).
- **Formatting**: After each task that touches PHP, run
  `./vendor/bin/sail pint --dirty --format agent`.
- **Before writing code that depends on an ecosystem API**, use the Boost `search-docs` tool to
  confirm Laravel 13 or Fortify 1.x syntax.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Approval gate, environment fix, and Fortify installation

- [x] T001 Confirm the user has approved the new top-level `app/` folders (`Actions`, `Console`, `Enums`, `Listeners`, `Notifications`, `Policies`, `Rules`) listed under Complexity Tracking in specs/001-multi-tenant-volunteers/plan.md. **Approved by the user on 2026-10-05.**
- [x] T002 [P] Set `APP_URL=http://localhost` in .env and .env.example so links in emails match the Sail port 80 (quickstart.md Prerequisites).
- [x] T003 Install Fortify with `./vendor/bin/sail composer require laravel/fortify:^1.40`, then `./vendor/bin/sail artisan fortify:install --no-interaction`. Confirm `App\Providers\FortifyServiceProvider` is registered in bootstrap/providers.php.
- [x] T004 If `fortify:install` published a two-factor migration (`database/migrations/*_add_two_factor_columns_to_users_table.php`), delete it. Two-step sign-in is out of scope (spec Clarifications). *Done: also deleted the published `*_create_passkeys_table.php` migration. Passkeys are another sign-in method, and the spec allows email and password only.*
- [x] T005 Configure config/fortify.php:
  - `'home' => '/dashboard'`
  - `'lowercase_usernames' => true`
  - `'views' => true`
  - `features` set to exactly `Features::resetPasswords()`, `Features::emailVerification()`, `Features::updateProfileInformation()` and `Features::updatePasswords()`. Registration and two-factor stay off (research R3).
- [x] T006 Delete app/Actions/Fortify/CreateNewUser.php and remove the `Fortify::createUsersUsing(...)` call from app/Providers/FortifyServiceProvider.php. Registration is disabled (R3). *Done: also removed the unused two-factor redirect and the `two-factor` and `passkeys` rate limiters from the provider and config/fortify.php.*

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, core models, authentication screens, organization middleware and layout.
Every story depends on these.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Tests for Foundational (write first, must fail)

- [x] T007 [P] Write tests/Feature/Auth/AuthenticationTest.php:
  - Login with a mixed-case email signs in the lowercase-stored user.
  - A wrong password fails.
  - The sixth attempt within a minute gets 429.
  - Logout works.
  - A guest opening `/dashboard` is redirected to `login`.
- [x] T008 [P] Write tests/Feature/Auth/PasswordResetTest.php:
  - The forgot-password form always shows the same message, whether or not the email exists.
  - `ResetPassword` is sent with `Notification::fake()` only for real accounts.
  - The reset link sets a new password.
- [x] T009 [P] Write tests/Feature/Auth/EmailVerificationTest.php:
  - An unverified user sees `verification.notice`.
  - The signed verify link sets `email_verified_at` and dispatches `Illuminate\Auth\Events\Verified`.
  - Resend is throttled at 6 per minute.
- [x] T010 [P] Write tests/Feature/Membership/EnsureActiveMembershipTest.php against `orgs.show`:
  - Guest → redirect to login.
  - Unverified member → verification notice.
  - Non-member → 404.
  - Member with `pending`, `inactive` or `left` status → 404.
  - Active member of a `pending` or `rejected` organization → 404.
  - Active member of a `suspended` organization → 403 with the suspended view.
  - Active member of an active organization → 200, showing the organization name and their role
    (FR-003, FR-004, FR-013, FR-016).
  *Done: also added tests/Feature/Policies/OrganizationPolicyTest.php covering the full `view` and `manageMembers` permission matrix, per the testing-best-practices skill. tests/TestCase.php calls `withoutVite()` so views render without a frontend build.*
- [x] T011 [P] Write tests/Feature/Auth/CreateAccountTest.php for `App\Actions\Accounts\CreateAccount`:
  - Stores a lowercased email, a hashed password, an optional phone and `adult_confirmed_at`.
  - Refuses creation when adult confirmation is missing (FR-045).
  - Fires `Illuminate\Auth\Events\Registered` only when `verified: false`.
  - Sets `email_verified_at` when `verified: true`.

### Implementation for Foundational

- [x] T012 [P] Create enums with `make:enum`, following the values and labels in data-model.md:
  - app/Enums/OrganizationStatus.php: Pending, Active, Rejected, Suspended.
  - app/Enums/MembershipRole.php: Administrator, Volunteer, with a `label(): string` method.
  - app/Enums/MembershipStatus.php: Pending, Active, Inactive, Left.
  - app/Enums/RosterStatus.php: Invited, Pending, Active, Inactive, Left, with a `label()`
    method ("Pending approval" for Pending) and `fromMembership(MembershipStatus): self`.
- [x] T013 Create the migration database/migrations/*_create_organizations_table.php with the columns in data-model.md: `name`, `slug` unique, `contact_email`, `status` indexed with default `pending`, `rejection_reason`, `requested_by_id` constrained to users, `signup_token` nullable unique, `self_signup_enabled` default true, and timestamps.
  - In `up()`, add the partial unique index with `DB::statement("CREATE UNIQUE INDEX organizations_name_lower_unique ON organizations (lower(name)) WHERE status <> 'rejected'")`.
  - Document in a PHPDoc block why raw SQL is needed: the schema builder has no partial-index API (research R6, Constitution I).
  - `down()` drops the table.
- [x] T014 Create the migration database/migrations/*_add_tenancy_columns_to_users_table.php (after T013) adding `phone` string(32) nullable, `adult_confirmed_at` timestamp nullable, `is_platform_operator` boolean default false, and `last_organization_id` foreignId nullable constrained to organizations with `nullOnDelete()`. `down()` drops the foreign key, then the columns.
- [x] T015 Create the migration database/migrations/*_create_memberships_table.php:
  - Columns: `organization_id` with `cascadeOnDelete()`, `user_id` with `cascadeOnDelete()`, `role`, `status`, `requested_at` nullable, `joined_at` nullable, timestamps.
  - Indexes: `unique(['organization_id','user_id'])`, `index(['organization_id','status'])`, `index('user_id')`.
  - `down()` drops the table.
- [x] T016 Run `./vendor/bin/sail artisan migrate` and `./vendor/bin/sail artisan migrate:rollback --step=3` then `./vendor/bin/sail artisan migrate` to prove the T013–T015 `down()` methods work (quality gate 4).
- [x] T017 [P] Create the model app/Models/Organization.php (`make:model Organization --factory`):
  - Mass-assignable attributes: `name` and `contact_email`.
  - Casts: `status` → `OrganizationStatus`, `self_signup_enabled` → boolean.
  - Relationships: `memberships()` HasMany, `requester()` BelongsTo User via `requested_by_id`.
  - `getRouteKeyName()` returns `slug`. *Done with the Laravel 13 `#[RouteKey('slug')]` attribute, matching the attribute style of `User`.*
  - Local scope `active()`.
  - `regenerateSignupToken(): void`: sets a random 40-character `signup_token` and saves. Used by US3 approval and US5 regeneration.
- [x] T018 [P] Create the model app/Models/Membership.php (`make:model Membership --factory`):
  - Casts: `role` → `MembershipRole`, `status` → `MembershipStatus`, `requested_at` and `joined_at` → datetime.
  - Relationships: `organization()` and `user()` BelongsTo.
  - `#[Scope]` local scopes `active()`, `administrators()` (active Administrators) and `awaitingApproval()` (`status=pending` with a verified user, research R9).
  - Helpers `isAdministrator(): bool` and `isActive(): bool`.
- [x] T019 Extend app/Models/User.php:
  - Implement `MustVerifyEmail`.
  - Add `phone` to `#[Fillable]`. Never add `adult_confirmed_at`, `is_platform_operator` or `last_organization_id`.
  - Casts: `adult_confirmed_at` → datetime, `is_platform_operator` → boolean.
  - Relationships: `memberships()` HasMany, `lastOrganization()` BelongsTo. Organizations are reached through `memberships.organization`; no BelongsToMany, because nothing needs it (Constitution IV).
  - `membershipIn(Organization): ?Membership`: a plain indexed lookup with no caching. A cached value would go stale when a test reuses one user object across requests (for example T101: deactivate, then expect 404).
- [x] T020 [P] Write the factories:
  - database/factories/OrganizationFactory.php: unique company name, slug from the name, `contact_email`, a `requested_by_id` user, and states `pending()`, `active()` (sets `signup_token`), `rejected()` (with a reason) and `suspended()`.
  - database/factories/MembershipFactory.php: states `administrator()`, `volunteer()`, `active()` (sets `joined_at`), `pending()` (sets `requested_at`), `inactive()` and `left()`.
- [x] T021 [P] Update database/factories/UserFactory.php: set `adult_confirmed_at => now()` by default, and add an `operator()` state that sets `is_platform_operator => true`.
- [x] T022 Create app/Actions/Accounts/CreateAccount.php (`make:class`) with `handle(array $attributes, bool $verified): User`. `$attributes` is shaped `array{name: string, email: string, password: string, phone?: ?string}`. It lowercases the email, sets `adult_confirmed_at`, marks the email verified when `$verified`, and fires `Registered` when not verified. Make T011 pass. *Done: the signature is `handle(array $attributes, bool $adultConfirmed, bool $verified)`, so the action itself refuses an account without adult confirmation (FR-045) even if a caller skips validation. The trait in T023 also provides `accountMessages()`.*
- [x] T023 Create the trait app/Actions/Fortify/AccountValidationRules.php alongside the published PasswordValidationRules. It provides `accountRules(): array` for `name`, `email` (`email:rfc`, `unique:users,email`), `password` (via `passwordRules()`), `phone` (nullable, max 32, regex `/^[0-9 +()\-]+$/`) and `adult_confirmation` (`accepted`), per data-model.md Validation summary. Custom message for `email.unique`: "An account with this email already exists. Sign in to continue."
- [x] T024 Configure app/Providers/AppServiceProvider.php `boot()`:
  - `Password::defaults()`: min 8, plus `uncompromised()` only when `app()->isProduction()`.
  - `RateLimiter::for('public-forms')`: 10 per minute by IP.
  - `RateLimiter::for('roster-import')`: 5 per minute by user id.
  - `Gate::define('operate-platform', ...)` checking `is_platform_operator`.
  - `Model::preventLazyLoading(! app()->isProduction())`, to catch N+1 queries in tests.
  (research R13, R14)
- [x] T025 [P] Create the base Blade layouts and components (Tailwind, labelled inputs, visible focus states):
  - resources/views/layouts/guest.blade.php.
  - resources/views/layouts/app.blade.php: header showing the current organization name on every authenticated page (FR-023), a profile link and a logout form. On `/orgs/...` pages the current organization is the one in the URL. On other pages (dashboard, profile, operator) it is the user's `lastOrganization`, if `OrganizationPolicy::view` still allows it. Otherwise "No organization selected".
  - resources/views/components/flash.blade.php.
  - resources/views/components/input.blade.php.
  - resources/views/components/status-badge.blade.php.
  *Done: also added resources/views/components/button.blade.php. The current organization comes from a view composer in AppServiceProvider, keeping queries out of Blade. The header's profile link is added in T068, when the `profile.edit` route exists.*
- [x] T026 [P] Create the auth views resources/views/auth/login.blade.php, forgot-password.blade.php, reset-password.blade.php and verify-email.blade.php, and register them in app/Providers/FortifyServiceProvider.php with `Fortify::loginView`, `requestPasswordResetLinkView`, `resetPasswordView` and `verifyEmailView`. Make T007–T009 pass. *Done: both Fortify reset-link responses are bound to app/Http/Responses/PasswordResetLinkRequestedResponse.php, so the form gives the same message whether or not the account exists (contracts/routes.md). The flash component translates Fortify status keys such as `verification-link-sent`.*
- [x] T027 Create app/Policies/OrganizationPolicy.php (`make:policy OrganizationPolicy --model=Organization`) with:
  - `view(User, Organization): Response`:
    - active membership in an active organization → `Response::allow()`;
    - active membership in a suspended organization → `Response::deny(code: 'organization-suspended')`;
    - anything else → `Response::denyAsNotFound()` (FR-004).
  - `manageMembers(User, Organization): bool`: true only for an active Administrator membership.
- [x] T028 Create app/Http/Middleware/EnsureActiveMembership.php (`make:middleware`), after T027:
  - Call `Gate::inspect('view', $organization)`. If allowed, continue.
  - If denied with code `organization-suspended`, return a 403 response rendering resources/views/orgs/suspended.blade.php.
  - Any other denial: abort 404. The middleware makes no membership checks of its own (Constitution I).
  - Register the alias `member` in bootstrap/app.php.
- [x] T029 Define the route skeleton in routes/web.php:
  - `GET /dashboard` named `dashboard` with `auth`.
  - A group with prefix `orgs/{organization:slug}`, name `orgs.`, middleware `['auth','verified','member']` and `->scopeBindings()`.
  - A group with prefix `operator`, name `operator.`, middleware `['auth','verified','can:operate-platform']`.
  - `GET /` inside the `orgs.` group named `orgs.show`.
  (contracts/routes.md)
- [x] T030 Create app/Http/Controllers/Org/OrganizationHomeController.php (invokable) and resources/views/orgs/show.blade.php. It shows the organization name and the viewer's role. For administrators it renders an admin navigation slot that later stories fill (FR-016). Also create resources/views/errors/403.blade.php: "You don't have access to this page", with a link to `orgs.show` when the URL has an `{organization}` (spec edge case). Make T010 pass.
- [x] T031 Create app/Http/Controllers/DashboardController.php (invokable) and resources/views/dashboard.blade.php:
  - If the user has exactly one active membership in an active organization, redirect to its `orgs.show`.
  - Otherwise list the active organizations.
  - Show a "verify your email" banner when the user is unverified.
  - Later stories add sections.
  *Done: the redirect applies only to verified users. Unverified users stay on the dashboard and see the verification banner.*
- [x] T032 Write database/seeders/DatabaseSeeder.php to create the quickstart.md seeded data (password `password`):
  - Operator.
  - Food Bank North and River Cleanup, both active, each with an admin.
  - A Food Bank North volunteer.
  - `both@example.test`: Volunteer in Food Bank North and Administrator in River Cleanup.
  - Community Kitchen, pending, with a verified requester.
  Verify with `./vendor/bin/sail artisan migrate:fresh --seed`.

**Checkpoint**: The schema migrates and rolls back. Sign-in, password reset and verification
work. Organization URLs are member-only. The tests from T007–T011 pass.

---

## Phase 3: User Story 1 - Administrator builds a separate volunteer roster (Priority: P1) 🎯 MVP

**Goal**: Administrators invite volunteers by email. Invitees create an account and become
active. Administrators browse, search and filter a roster that only ever contains their own
organization.

**Independent Test**: Two active organizations, each with an administrator. Each admin invites
volunteers who accept. Each admin sees, searches and opens only their own members. Direct links
to the other organization's members return 404.

### Tests for User Story 1 (write first, must fail)

- [ ] T033 [P] [US1] Write tests/Feature/TenantIsolationTest.php. It walks every route named `orgs.*` from `Route::getRoutes()` and fills `{organization}`, `{membership}` and `{invitation}` using Organization B's records:
  - (a) An active admin of Organization A requesting Organization B's URLs gets 404 for every method.
  - (b) That admin requesting Organization A's URL with a child record from Organization B gets 404.
  - (c) A guest requesting any registered `orgs.*`, `operator.*`, `dashboard` or `profile.edit` route is redirected to `login`. Match by name or prefix among routes that exist, so the test passes before later stories add theirs.
  - (d) A verified non-operator requesting any `operator.*` route gets 403.
  - Routes are read from the route list on every run, so routes added in later stories are swept automatically (Constitution II: guest, unauthorized and authorized cases for every endpoint).
  - It fails if a route uses a parameter it can't resolve, so new routes can't skip coverage (SC-001, research R15).
- [ ] T034 [P] [US1] Write tests/Feature/Roster/RosterIndexTest.php for `orgs.members.index`:
  - The admin sees only their own organization's memberships and open invitations, with status labels.
  - `q` matches name or email without regard to case, and escapes `%`.
  - `role` and `status` filters work, including `status=invited`.
  - Unverified pending memberships are excluded.
  - 25 per page, with a stable order.
  - A volunteer gets 403.
  (FR-005, FR-035, FR-036, FR-039)
- [ ] T035 [P] [US1] Write tests/Feature/Roster/MemberShowTest.php for `orgs.members.show`: the admin sees name, email, phone, role, status and joined date, with no edit fields for personal details (FR-037). A volunteer gets 403, and a membership from another organization gets 404.
- [ ] T036 [P] [US1] Write tests/Feature/Invitations/IssueInvitationTest.php for `orgs.invitations.store`:
  - Creates an invitation with a lowercased email, a hashed token, a 7-day expiry and the chosen role.
  - Sends `InvitationNotification` on demand (`Notification::assertSentOnDemand`).
  - Refuses an email with a pending, active or inactive membership, or an open invitation (FR-034).
  - Allows an email whose membership is `left`.
  - Validation errors for a missing name or an invalid email.
  - A volunteer gets 403.
  (FR-030, FR-031)
- [ ] T037 [P] [US1] Write tests/Feature/Invitations/ManageInvitationTest.php:
  - `orgs.invitations.resend` replaces the token hash and expiry, and the old token's `invitations.show` reports invalid.
  - `orgs.invitations.destroy` deletes the invitation.
  - Another organization's invitation gets 404.
  (FR-033)
- [ ] T038 [P] [US1] Write tests/Feature/Invitations/AcceptInvitationNewAccountTest.php for `invitations.show` and `invitations.accept`:
  - New email: the setup form has the email fixed and the name pre-filled.
  - Accepting with the account fields creates a **verified** user and an active membership with the invitation's role and `joined_at`, deletes the invitation, signs the user in, and redirects to `orgs.show`.
  - Missing adult confirmation → error and no user (FR-045).
  - An expired or unknown token → "ask your administrator" with no changes.
  - Throttled by `public-forms`.
  (FR-031, FR-032)

### Implementation for User Story 1

- [ ] T039 [US1] Create the migration database/migrations/*_create_invitations_table.php:
  - Columns: `organization_id` with `cascadeOnDelete()`, `invited_by_id` constrained to users, `name`, `email`, `role`, `token_hash` string(64) unique, `expires_at`, timestamps.
  - Index: `unique(['organization_id','email'])`.
  - `down()` drops the table.
  - Run migrate, then rollback and migrate again.
- [ ] T040 [US1] Create the model app/Models/Invitation.php (`make:model Invitation --factory`):
  - Casts: `role` → `MembershipRole`, `expires_at` → datetime.
  - Relationships: `organization()` and `invitedBy()` BelongsTo.
  - `isExpired(): bool`.
  - `static findByToken(string $plainToken): ?self`, which looks up by `hash('sha256', $plainToken)`.
  Add `invitations()` HasMany to app/Models/Organization.php.
- [ ] T041 [P] [US1] Write database/factories/InvitationFactory.php with a default 7-day expiry and an `expired()` state. Token hashes are generated from a known plain token that the test can use.
- [ ] T042 [P] [US1] Create app/Notifications/InvitationNotification.php (`make:notification`). It's queued and receives the organization name, inviter name, plain token, expiry and an `$existingAccount` flag. The mail says "set up your account" or "sign in to accept" and links to `route('invitations.show', $token)`. It must not mention any other organization (contracts/notifications.md).
- [ ] T043 [US1] Create app/Actions/Invitations/IssueInvitation.php with two methods:
  - `handle(Organization, User $inviter, string $name, string $email, MembershipRole): Invitation` checks the FR-034 duplicate rule, throws `ValidationException` on `email`, then calls `issue()`.
  - `issue(...)` creates the row with a random 40-character token stored as a SHA-256 hash and `expires_at = now()->addDays(7)`, then sends `InvitationNotification` on demand with `afterCommit()`. ImportRoster reuses it in US6.
- [ ] T044 [US1] Create app/Actions/Invitations/ResendInvitation.php. It sets a new token hash and expiry, sets `invited_by_id` to the current admin, and re-sends the notification.
- [ ] T045 [US1] Create app/Actions/Invitations/AcceptInvitation.php for the **new-account path**. In a transaction it:
  - calls `CreateAccount` with `verified: true`;
  - creates an active Membership with the invitation's role and `joined_at=now`;
  - deletes the invitation.
  It returns the membership. (US4 adds the existing-account path.)
- [ ] T046 [US1] Create app/Actions/Roster/BuildRosterQuery.php with `handle(Organization, ?string $search, ?MembershipRole, ?RosterStatus): Builder`. It returns `DB::query()->fromSub(...)` over a `UNION ALL` of:
  - memberships joined to users, excluding unverified pending ones;
  - the organization's open invitations.
  Columns are as listed in data-model.md "Roster entry". It applies case-insensitive `whereLike` with `%` and `_` escaped, plus the role and status filters, and orders by `name`, `kind`, `key`.
- [ ] T047 [P] [US1] Create the form requests:
  - app/Http/Requests/RosterFilterRequest.php: `q`, `role` and `status` rules from data-model.md.
  - app/Http/Requests/StoreInvitationRequest.php: `name`, `email`, `role`, with the email lowercased in `prepareForValidation`.
  - app/Http/Requests/AcceptInvitationRequest.php: uses `AccountValidationRules`, without `email`, which comes from the invitation.
  All authorize with `OrganizationPolicy::manageMembers` where an organization is in the route.
- [ ] T048 [US1] Create app/Http/Controllers/Org/MemberController.php with `index` (paginates `BuildRosterQuery` at 25 per page with the query string) and `show` (eager-loads `user`). Register `orgs.members.index` and `orgs.members.show` in routes/web.php.
- [ ] T049 [US1] Create app/Http/Controllers/Org/InvitationController.php with `create`, `store`, `resend` and `destroy`, calling the actions from T043 and T044. Register `orgs.invitations.create`, `.store`, `.resend` and `.destroy` in routes/web.php (scoped bindings resolve `{invitation}` through `$organization->invitations()`).
- [ ] T050 [US1] Create app/Http/Controllers/InvitationAcceptanceController.php with `show` and `accept`, both throttled by `public-forms`. It resolves the token with `Invitation::findByToken` and handles unknown or expired tokens with a message. Register `invitations.show` and `invitations.accept` in routes/web.php.
- [ ] T051 [P] [US1] Create the views:
  - resources/views/orgs/members/index.blade.php: GET search and filter form, table, status badges, pagination links, and resend and cancel buttons on invited rows.
  - resources/views/orgs/members/show.blade.php: read-only personal details.
  - resources/views/orgs/invitations/create.blade.php.
  - resources/views/invitations/show.blade.php: setup form, expired message.
- [ ] T052 [US1] Add the admin links "Roster" and "Invite volunteer" to resources/views/orgs/show.blade.php, behind `@can('manageMembers', $organization)`.
- [ ] T053 [US1] Run `./vendor/bin/sail artisan test --compact tests/Feature/Roster tests/Feature/Invitations tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: US1 is complete and demoable on its own (quickstart.md US1).

---

## Phase 4: User Story 2 - Volunteer requests to join through the sign-up link (Priority: P2)

**Goal**: A person opens an organization's sign-up link, creates an account or signs in,
verifies their email, and requests to join. Administrators approve or decline. Approved
volunteers can manage their own profile but can't see the roster.

**Independent Test**: With one active organization and an administrator: register through the
sign-up link, verify, request to join, have the admin approve, then confirm the volunteer can
edit their profile and gets 403 on the roster.

### Tests for User Story 2 (write first, must fail)

- [ ] T054 [P] [US2] Write tests/Feature/JoinRequests/JoinPageTest.php for `join.show`: it shows the organization name for a valid token. An unknown token shows "no longer valid". An organization that isn't active, or has self sign-up off, shows "not accepting sign-ups" (FR-025).
- [ ] T055 [P] [US2] Write tests/Feature/JoinRequests/SubmitJoinRequestTest.php for `join.store`:
  - A guest with account fields gets a new **unverified** user, is signed in, and gets a `pending` membership with `requested_at`. `VerifyEmail` is sent, and `JoinRequestReceived` is **not** sent yet.
  - Adult confirmation is required.
  - A signed-in verified user gets a pending membership, and the admins are notified immediately.
  - A signed-out person with an existing account follows "Sign in" from `join.show`, is returned to `join.show` after login, and submits without account fields (FR-026).
  - A guest submitting an existing account's email gets the "Sign in to continue" error.
  - Already active, pending or inactive → message with no change.
  - `left` → back to `pending`.
  - After a decline, a new request is allowed.
  - Throttled.
  (FR-026, FR-034, FR-041, FR-045, edge cases)
- [ ] T056 [P] [US2] Write tests/Feature/JoinRequests/VerificationReleaseTest.php: dispatching `Verified` for a user with pending memberships sends `JoinRequestReceived` to every active Administrator of those organizations, and to no one else (R9).
- [ ] T057 [P] [US2] Write tests/Feature/JoinRequests/ReviewJoinRequestTest.php:
  - `orgs.join-requests.index` lists only verified pending requests, paginated.
  - Approve → active with `joined_at`, and `JoinRequestApproved` is sent.
  - Decline (`destroy`) → the membership row is deleted, and `JoinRequestDeclined` is sent.
  - A volunteer gets 403.
  (FR-027, FR-028)
- [ ] T058 [P] [US2] Write tests/Feature/Auth/ProfileTest.php:
  - `profile.edit` renders.
  - `user-profile-information.update` changes `name` and `phone` and ignores `email`.
  - `user-password.update` requires `current_password`.
  (FR-043)
- [ ] T059 [P] [US2] Write tests/Feature/Membership/VolunteerAccessTest.php: an active volunteer gets 200 on `orgs.show` and 403 on `orgs.join-requests.index`, `orgs.join-requests.approve` and `orgs.settings.edit` (FR-016; US2 scenario 5). The roster and member-page 403 checks live in US1 (T034, T035), so this story doesn't depend on US1.

### Implementation for User Story 2

- [ ] T060 [US2] Add `acceptsSignups(): bool` (active and `self_signup_enabled`) to app/Models/Organization.php.
- [ ] T061 [P] [US2] Create the queued notifications per contracts/notifications.md:
  - app/Notifications/JoinRequestReceived.php
  - app/Notifications/JoinRequestApproved.php
  - app/Notifications/JoinRequestDeclined.php
- [ ] T062 [US2] Create app/Actions/Memberships/RequestToJoin.php with `handle(Organization, User): Membership`:
  - Applies the duplicate rule (FR-034).
  - Reuses a `left` row, setting it to `pending` with `requested_at`.
  - If the user is verified, sends `JoinRequestReceived` to `$organization->memberships()->administrators()` users.
- [ ] T063 [P] [US2] Create app/Actions/Memberships/ApproveJoinRequest.php (status `active`, `joined_at`, notify) and app/Actions/Memberships/DeclineJoinRequest.php (delete the row, notify on demand to the user's email).
- [ ] T064 [US2] Create app/Listeners/SendPendingRequestNotifications.php (`make:listener --event=Illuminate\\Auth\\Events\\Verified`). For each of the user's pending memberships, it notifies that organization's active Administrators with `JoinRequestReceived`. US3 adds organization requests. It relies on event discovery.
- [ ] T065 [P] [US2] Create app/Http/Requests/StoreJoinRequest.php. It applies `AccountValidationRules` only when the request is from a guest, and lowercases the email.
- [ ] T066 [US2] Create app/Http/Controllers/JoinController.php with `show` and `store` (throttle `public-forms`). It finds the organization by `signup_token`. For guests, `show` sets the intended URL (`redirect()->setIntendedUrl(url()->current())`) so Fortify returns them here after login. `store` calls `CreateAccount` with `verified: false` for guests, signs them in, then calls `RequestToJoin` and redirects to `dashboard`. Register `join.show` and `join.store` in routes/web.php.
- [ ] T067 [US2] Create app/Http/Controllers/Org/JoinRequestController.php with `index` (`awaitingApproval()` with `user`, paginated), `approve` and `destroy`. Register `orgs.join-requests.index`, `.approve` and `.destroy` in routes/web.php.
- [ ] T068 [US2] Update app/Actions/Fortify/UpdateUserProfileInformation.php to validate and update only `name` and `phone`. Create app/Http/Controllers/ProfileController.php (`edit`) and resources/views/profile/edit.blade.php with forms posting to `user-profile-information.update` and `user-password.update`. Register `profile.edit` in routes/web.php.
- [ ] T069 [US2] Create app/Http/Controllers/Org/SettingsController.php with `edit` only for now, and resources/views/orgs/settings/edit.blade.php showing the sign-up link (`route('join.show', $organization->signup_token)`) with a copy-friendly read-only input. Register `orgs.settings.edit`. US5 adds updates.
- [ ] T070 [P] [US2] Create the views:
  - resources/views/join/show.blade.php: account fields for guests with an "Already have an account? Sign in" link to `login`, a "request to join" button for signed-in users, and an invalid / not-accepting message.
  - resources/views/orgs/join-requests/index.blade.php.
  Add a "Join requests" link with a count, and a "Settings" link, to resources/views/orgs/show.blade.php.
- [ ] T071 [US2] Add a "Your join requests" section (organization name and "awaiting approval" or "verify your email first") to resources/views/dashboard.blade.php and app/Http/Controllers/DashboardController.php.
- [ ] T072 [US2] Run `./vendor/bin/sail artisan test --compact tests/Feature/JoinRequests tests/Feature/Auth/ProfileTest.php tests/Feature/Membership/VolunteerAccessTest.php tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: US1 and US2 both work on their own (quickstart.md US2).

---

## Phase 5: User Story 3 - A new organization is requested and approved (Priority: P3)

**Goal**: Anyone requests an organization, and it stays pending until an operator approves it.
On approval, the requester becomes its first administrator. Operators can reject with a reason,
and can suspend or reinstate.

**Independent Test**: Submit an organization request, approve it as an operator, then sign in as
the requester and confirm admin access with an empty roster.

### Tests for User Story 3 (write first, must fail)

- [ ] T073 [P] [US3] Write tests/Feature/Organizations/RequestOrganizationTest.php for `organization-requests.create` and `organization-requests.store`:
  - A guest with account fields gets an unverified user (signed in) and a `pending` organization with `requested_by_id`, a slug and `contact_email`.
  - A signed-in user needs no account fields.
  - A signed-out person with an existing account follows "Sign in" and is returned to `organization-requests.create` after login (FR-009).
  - Name uniqueness ignores case against pending, active and suspended organizations, but allows a rejected organization's name.
  - A duplicate slug gets a suffix.
  - Adult confirmation is required.
  - Throttled.
  - The dashboard shows "awaiting approval".
  (FR-007, FR-009, FR-010, FR-045)
- [ ] T074 [P] [US3] Write tests/Feature/Organizations/OrganizationNameConstraintTest.php: inserting two organizations whose names differ only by case, both non-rejected, throws `UniqueConstraintViolationException` at the database level. That proves the T013 partial index.
- [ ] T075 [P] [US3] Write tests/Feature/Operator/OperatorAccessTest.php:
  - A non-operator gets 403 on every `operator.*` route.
  - `operator.organizations.index` defaults to pending and hides pending organizations whose requester is unverified.
  - It shows name, status, contact email, requester, created date and active member count.
  - `operator.organizations.show` shows no roster or member data.
  (FR-011, FR-014)
- [ ] T076 [P] [US3] Write tests/Feature/Organizations/ReviewOrganizationTest.php:
  - Approve → `active`, `signup_token` set, requester has an active Administrator membership, `OrganizationApproved` sent.
  - Reject requires `reason` → `rejected` with the reason, `OrganizationRejected` sent with the reason, and the name can be requested again.
  - Approving or rejecting a non-pending organization → 409.
  (FR-012)
- [ ] T077 [P] [US3] Write tests/Feature/Organizations/SuspendOrganizationTest.php:
  - Suspend an active organization → its members get the 403 suspended page, and active admins get `OrganizationSuspended`.
  - Reinstate → access returns.
  - Suspending a non-active organization or reinstating a non-suspended one → 409.
  - A member of the suspended organization keeps access to their other organizations.
  (FR-013)
- [ ] T078 [P] [US3] Write tests/Feature/Organizations/OrganizationRequestNotificationTest.php: a verified requester's submission sends `OrganizationRequested` to every operator immediately. An unverified requester's submission sends it only once `Verified` is dispatched (R9).
- [ ] T079 [P] [US3] Write tests/Feature/Console/OperatorCommandsTest.php: `app:grant-operator {email}` sets the flag, `app:revoke-operator {email}` clears it, and an unknown email exits with failure.

### Implementation for User Story 3

- [ ] T080 [P] [US3] Create app/Rules/UniqueOrganizationName.php (`make:rule`). It fails when `Organization::query()->whereNot('status', OrganizationStatus::Rejected)->whereLike('name', $value, caseSensitive: false)` finds a row, with `%`, `_` and `\` escaped in `$value` so the match is exact. It accepts an optional organization to ignore (`whereKeyNot`). No raw SQL: the database index from T013 still catches races (T074).
- [ ] T081 [P] [US3] Create the queued notifications per contracts/notifications.md:
  - app/Notifications/OrganizationRequested.php
  - app/Notifications/OrganizationApproved.php
  - app/Notifications/OrganizationRejected.php
  - app/Notifications/OrganizationSuspended.php
- [ ] T082 [US3] Create app/Actions/Organizations/RequestOrganization.php with `handle(User $requester, string $name, string $contactEmail): Organization`. It generates a unique slug (`Str::slug` plus a numeric suffix), creates the organization as `pending`, and notifies operators (`User::where('is_platform_operator', true)`) only if the requester is verified.
- [ ] T083 [P] [US3] Create the transactional actions. Each guards the current status and throws `ConflictHttpException` (409) when it doesn't apply:
  - app/Actions/Organizations/ApproveOrganization.php: status `active`, `regenerateSignupToken()`, an Administrator membership for the requester, notify.
  - app/Actions/Organizations/RejectOrganization.php: reason, notify.
  - app/Actions/Organizations/SuspendOrganization.php: notify active admins.
  - app/Actions/Organizations/ReinstateOrganization.php.
- [ ] T084 [US3] Extend app/Listeners/SendPendingRequestNotifications.php to also send `OrganizationRequested` to operators for each pending organization the newly verified user requested.
- [ ] T085 [P] [US3] Create the form requests:
  - app/Http/Requests/StoreOrganizationRequest.php: `organization_name` with `UniqueOrganizationName`, max 120; `contact_email`; plus `AccountValidationRules` for guests.
  - app/Http/Requests/RejectOrganizationRequest.php: `reason` required, max 1000.
- [ ] T086 [US3] Create app/Http/Controllers/OrganizationRequestController.php with `create` and `store`. `create` sets the intended URL for guests, as in T066. `store` is throttled by `public-forms` and calls `CreateAccount` with `verified: false` for guests, then `RequestOrganization`, redirect to `dashboard`). Register `organization-requests.create` and `organization-requests.store` in routes/web.php.
- [ ] T087 [US3] Create the operator controllers and register their `operator.organizations.*` routes in routes/web.php:
  - app/Http/Controllers/Operator/OrganizationController.php: `index` filtered by `status` with `withCount` of active memberships, eager-loading `requester`, paginated; and `show`.
  - app/Http/Controllers/Operator/OrganizationReviewController.php: `approve` and `reject`.
  - app/Http/Controllers/Operator/OrganizationSuspensionController.php: `suspend` and `reinstate`.
- [ ] T088 [P] [US3] Create the console commands app/Console/Commands/GrantOperator.php (`app:grant-operator {email}`) and app/Console/Commands/RevokeOperator.php (`app:revoke-operator {email}`) with `make:command`.
- [ ] T089 [P] [US3] Create the views:
  - resources/views/organization-requests/create.blade.php: account fields for guests with an "Already have an account? Sign in" link.
  - resources/views/operator/organizations/index.blade.php: status tabs, table.
  - resources/views/operator/organizations/show.blade.php: approve, reject-with-reason, suspend and reinstate forms.
  Add a "Request an organization" link to resources/views/welcome.blade.php, and an "Operator" header link for operators to resources/views/layouts/app.blade.php.
- [ ] T090 [US3] Add a "Your organization requests" section (pending: "awaiting approval"; rejected: the reason) to resources/views/dashboard.blade.php and app/Http/Controllers/DashboardController.php.
- [ ] T091 [US3] Run `./vendor/bin/sail artisan test --compact tests/Feature/Organizations tests/Feature/Operator tests/Feature/Console tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: US1, US2 and US3 all work on their own (quickstart.md US3).

---

## Phase 6: User Story 4 - A person belongs to several organizations (Priority: P4)

**Goal**: One account works across organizations, with the last-used organization remembered, a
switcher on every page, separate roles per organization, existing-account invitation
acceptance, and no cross-organization leakage.

**Independent Test**: One person is a Volunteer in Organization A and an Administrator in
Organization B. Signing in and switching shows only the current organization's data with the
right role. Neither organization's admins can see the other membership.

### Tests for User Story 4 (write first, must fail)

- [ ] T092 [P] [US4] Write tests/Feature/Membership/OrganizationSwitchingTest.php:
  - Opening any `orgs.*` page updates `users.last_organization_id`.
  - `dashboard` redirects to the last organization when it's still accessible, and to the only active one otherwise.
  - With several organizations and no usable last organization, it shows a chooser that lists suspended organizations as not clickable.
  - A user whose only organization is suspended sees "This organization is suspended" on the dashboard, with no link into it (spec edge case).
  - The header switcher lists only active memberships in active organizations, each linking to `orgs.show`.
  - The role shown changes per organization.
  - On `dashboard` and `profile.edit`, the header shows the last organization's name, or "No organization selected" when there is none.
  (FR-021, FR-022, FR-023, SC-006)
- [ ] T093 [P] [US4] Write tests/Feature/Invitations/AcceptInvitationExistingAccountTest.php:
  - Invitation to an existing user's email: a signed-out visitor sees a sign-in prompt that returns to the invitation after login.
  - Signed in as that user, accepting creates an active membership without changing the password.
  - A `left` membership row is reused, with `joined_at` reset.
  - Signed in as a different user → "This invitation is for another email address" with no change.
  - `InvitationNotification` uses the "sign in to accept" wording.
  (FR-031; US4 scenario 3)
- [ ] T094 [P] [US4] Write tests/Feature/Membership/CrossOrganizationPrivacyTest.php:
  - An admin of A viewing a member who also belongs to B sees nothing naming B, on both `orgs.members.show` and `orgs.members.index`.
  - Deactivating the member in A leaves B's membership active.
  - Pending invitations shown on the dashboard name only their own organization.
  (FR-006, FR-020; US4 scenarios 4–5)

### Implementation for User Story 4

- [ ] T095 [US4] Update app/Http/Middleware/EnsureActiveMembership.php to set `last_organization_id` on the user when it differs from the current organization (a single update query, skipped when unchanged).
- [ ] T096 [US4] Update app/Http/Controllers/DashboardController.php and resources/views/dashboard.blade.php:
  - Redirect to `lastOrganization` when the user still has an active membership there and it's active.
  - Otherwise redirect to the only active organization.
  - Otherwise render the chooser (active organizations as links, suspended ones as non-clickable entries), with a "Pending invitations" section listing open, unexpired invitations for the user's email with accept links.
  - Eager-load `memberships.organization`.
- [ ] T097 [P] [US4] Create resources/views/components/org-switcher.blade.php and include it in the resources/views/layouts/app.blade.php header. It takes the user's active memberships in active organizations, passed by a view composer registered in app/Providers/AppServiceProvider.php with eager loading, and highlights the current organization.
- [ ] T098 [US4] Extend app/Actions/Invitations/AcceptInvitation.php with the **existing-account path**:
  - The signed-in user's email must equal the invitation's email.
  - Upsert the membership: reuse a `left` row, otherwise create one; set it active with the invitation's role and `joined_at=now`.
  - Delete the invitation.
- [ ] T099 [US4] Update app/Http/Controllers/InvitationAcceptanceController.php and resources/views/invitations/show.blade.php to handle three cases:
  - Existing account, signed out: show a sign-in prompt and call `redirect()->setIntendedUrl(...)` back to the invitation.
  - Signed in as a different email: refusal message.
  - Signed in as the invitee: an "Accept" button.
- [ ] T100 [US4] Run `./vendor/bin/sail artisan test --compact tests/Feature/Membership tests/Feature/Invitations tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: US1 to US4 all work on their own (quickstart.md US4).

---

## Phase 7: User Story 5 - Administrator manages member status and roles (Priority: P5)

**Goal**: Administrators can deactivate, reactivate, promote and demote members, and edit
organization settings including the sign-up link. Members can leave. The organization always
keeps an administrator.

**Independent Test**: Deactivate and reactivate a volunteer, promote a volunteer, confirm the
only admin can't demote themselves or leave, and regenerate the sign-up link and confirm the old
one fails.

### Tests for User Story 5 (write first, must fail)

- [ ] T101 [P] [US5] Write tests/Feature/Roster/UpdateMemberTest.php for `orgs.members.update`:
  - Deactivate → status `inactive`, and that member then gets 404 on `orgs.show`.
  - Reactivate → access returns, and `joined_at` is unchanged.
  - Promote → the member gets 200 on `orgs.members.index`.
  - Demote works, and the demoted admin's next request to `orgs.members.index` gets 403 with the "access denied" page linking to `orgs.show`.
  - Demoting or deactivating the last active admin → error "must keep at least one administrator".
  - Setting `status` to `pending` or `left` fails validation.
  - A volunteer gets 403, and another organization's membership gets 404.
  (FR-011, FR-017, FR-018, FR-038)
- [ ] T102 [P] [US5] Write tests/Feature/Membership/LeaveOrganizationTest.php: `orgs.membership.destroy` sets the member's status to `left` and redirects to `dashboard`. The last active admin is refused. A member who left can request again through the sign-up link (FR-024).
- [ ] T103 [P] [US5] Write tests/Feature/Organizations/OrganizationSettingsTest.php:
  - `orgs.settings.update` changes the name (ignoring case, unique except its own row) and the contact email, while the slug stays the same.
  - Toggling `self_signup_enabled` off makes `join.show` say "not accepting sign-ups".
  - `orgs.signup-link.store` replaces the token, and the old link says "no longer valid".
  - A volunteer gets 403.
  (FR-008, FR-029)

### Implementation for User Story 5

- [ ] T104 [US5] Create app/Actions/Memberships/ChangeMembership.php with `handle(Membership, ?MembershipRole $role, ?MembershipStatus $status): Membership`. Inside `DB::transaction` it:
  - locks the organization row with `lockForUpdate()`;
  - if the change would remove an active Administrator, counts the *other* active Administrators and throws `ValidationException` when there are none;
  - applies the change.
  Document the lock in PHPDoc (research R8).
- [ ] T105 [P] [US5] Create the form requests:
  - app/Http/Requests/UpdateMemberRequest.php: `role` as an enum; `status` as `Rule::enum(MembershipStatus::class)->only([Active, Inactive])`; at least one of them required.
  - app/Http/Requests/UpdateOrganizationSettingsRequest.php: `name` with `UniqueOrganizationName` ignoring the current organization, `contact_email`, `self_signup_enabled` boolean.
- [ ] T106 [US5] Add `update` to app/Http/Controllers/Org/MemberController.php (it calls `ChangeMembership`), and register `orgs.members.update` (PATCH) in routes/web.php.
- [ ] T107 [US5] Create app/Http/Controllers/Org/MembershipController.php with `destroy` (leave via `ChangeMembership` with status `Left`), and register `orgs.membership.destroy` (DELETE) in routes/web.php.
- [ ] T108 [US5] Add `update` to app/Http/Controllers/Org/SettingsController.php. Create app/Http/Controllers/Org/SignupLinkController.php with `store` (calls `regenerateSignupToken()`). Register `orgs.settings.update` (PATCH) and `orgs.signup-link.store` (POST) in routes/web.php.
- [ ] T109 [P] [US5] Update the views:
  - resources/views/orgs/members/show.blade.php: role select and activate/deactivate forms.
  - resources/views/orgs/settings/edit.blade.php: name, contact email, self sign-up toggle, regenerate button.
  - resources/views/orgs/show.blade.php: "Leave organization" form button with an explanatory sentence, without a JS confirm dialog.
- [ ] T110 [US5] Run `./vendor/bin/sail artisan test --compact tests/Feature/Roster tests/Feature/Membership tests/Feature/Organizations tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: US1 to US5 all work on their own (quickstart.md US5).

---

## Phase 8: User Story 6 - Administrator imports and exports the roster (Priority: P6)

**Goal**: Upload a CSV to invite up to 1,000 volunteers at once, with a per-row skip report, and
download the filtered roster as CSV.

**Independent Test**: Upload a file with valid rows, a duplicate, an invalid email and an
existing member. Valid rows are invited and the others are reported with reasons. The exported
file has exactly the organization's filtered members.

### Tests for User Story 6 (write first, must fail)

- [ ] T111 [P] [US6] Write tests/Unit/Roster/ParseRosterCsvTest.php (`--unit`) using fixture strings:
  - Header columns are matched without regard to case or spaces, and a BOM is stripped.
  - Extra columns are ignored, and blank lines are skipped and not counted.
  - Row numbers start at 2.
  - A missing `name` or `email` column, more than 1,000 rows, and no data rows each throw the exact message from contracts/csv-formats.md.
- [ ] T112 [P] [US6] Write tests/Unit/Roster/WriteRosterCsvTest.php (`--unit`):
  - Header `Name,Email,Phone,Role,Status,Date joined`.
  - The output starts with a UTF-8 BOM.
  - Role and status labels.
  - `YYYY-MM-DD` dates, empty when null.
  - Cells starting with `=`, `+`, `-`, `@`, a tab or a carriage return are prefixed with `'`.
  (R11)
- [ ] T113 [P] [US6] Write tests/Feature/Import/ImportRosterTest.php for `orgs.imports.store`:
  - A 200-row file → 200 invitations as Volunteer, with `InvitationNotification` sent on demand 200 times.
  - Mixed file → skip reasons "Missing name", "Invalid email", "Duplicate of row n" and "Already in roster", with row numbers.
  - An email belonging only to another organization is invited, and the report doesn't mention that organization.
  - Whole-file rejections (unreadable, missing columns, 1,001 rows, empty) → validation error with zero invitations.
  - A volunteer gets 403.
  - Throttled by `roster-import`.
  (FR-046 to FR-049)
- [ ] T114 [P] [US6] Write tests/Feature/Roster/ExportRosterTest.php:
  - `orgs.members.export` streams `text/csv` with filename `{slug}-roster-{YYYY-MM-DD}.csv`.
  - Rows match the `q`, `role` and `status` filters and contain only the organization's entries, including invited ones.
  - `orgs.imports.sample` returns the sample from contracts/csv-formats.md.
  - A volunteer gets 403.
  (FR-050, FR-051)

### Implementation for User Story 6

- [ ] T115 [P] [US6] Create app/Actions/Roster/ParseRosterCsv.php with `handle(string $path): array`, returning `list<array{row: int, name: string, email: string}>`. It uses `SplFileObject` in CSV mode, applies the header rules and the 1,000-row limit, and throws `ValidationException` on `file` with the contract messages.
- [ ] T116 [P] [US6] Create app/Actions/Roster/WriteRosterCsv.php with `toStream($handle, iterable $entries): void`. It writes the BOM, header and escaped rows with `fputcsv`.
- [ ] T117 [US6] Create app/Actions/Roster/ImportRoster.php with `handle(Organization, User $inviter, string $path): array`, returning `array{invited: int, skipped: list<array{row: int, email: ?string, reason: string}>}`. It:
  - parses the file;
  - loads the roster's existing emails in **one** query (memberships with status pending, active or inactive joined to users, plus open invitations);
  - validates each row (`email:rfc`, non-empty name);
  - tracks emails already seen in the file;
  - calls `IssueInvitation::issue()` for each valid row inside one `DB::transaction`.
- [ ] T118 [P] [US6] Create app/Http/Requests/ImportRosterRequest.php: `file` required, `file`, `mimes:csv,txt`, `max:1024`. Authorize with `manageMembers`.
- [ ] T119 [US6] Create app/Http/Controllers/Org/RosterImportController.php:
  - `create`.
  - `store`, throttled by `roster-import`: calls `ImportRoster`, flashes the report, and redirects to `orgs.imports.create`.
  - `sample`: `streamDownload` of the sample.
  Register `orgs.imports.create`, `orgs.imports.store` and `orgs.imports.sample` in routes/web.php.
- [ ] T120 [US6] Create app/Http/Controllers/Org/MemberExportController.php (invokable). It validates with `RosterFilterRequest`, then streams `BuildRosterQuery` results with `lazy()` through `WriteRosterCsv` via `response()->streamDownload()`. Register `orgs.members.export` in routes/web.php **before** `orgs.members.show`, so `export` isn't bound as a `{membership}`.
- [ ] T121 [P] [US6] Create resources/views/orgs/imports/create.blade.php: upload form, sample link, and the flashed report with counts and a skipped-rows table. Add "Import" and "Export (current filters)" links to resources/views/orgs/members/index.blade.php.
- [ ] T122 [US6] Run `./vendor/bin/sail artisan test --compact tests/Unit/Roster tests/Feature/Import tests/Feature/Roster tests/Feature/TenantIsolationTest.php` until it is green.

**Checkpoint**: All six user stories work on their own (quickstart.md US6).

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Performance checks, quality gates and end-to-end validation

- [ ] T123 [P] Create database/seeders/RosterPerformanceSeeder.php (local only, not called from DatabaseSeeder). It creates 500 organizations totalling 50,000 memberships, one of which has 1,000 members, using factories with bulk inserts. Run it, then confirm that organization's roster page and a search render in under 2 seconds (SC-007, SC-008).
- [ ] T124 [P] Time a 1,000-row import against seeded data, then run `./vendor/bin/sail artisan queue:listen` and confirm the report appears in under 2 minutes (SC-010).
- [ ] T125 [P] Do a best-effort accessibility pass on every view under resources/views/: labelled inputs, error messages linked to fields, visible focus styles, readable contrast, and keyboard-only operation (spec Assumptions).
- [ ] T126 [P] Run `./vendor/bin/sail composer audit` and resolve or record any advisories.
- [ ] T127 Run `./vendor/bin/sail pint --format agent` and confirm no changes remain (format gate).
- [ ] T128 Run `./vendor/bin/sail artisan migrate:fresh --seed`, then `./vendor/bin/sail artisan migrate:rollback` back to zero, then `./vendor/bin/sail artisan migrate` (migration gate).
- [ ] T129 Run the full suite with `./vendor/bin/sail artisan test --compact` and confirm it's all green, with no skipped tests (test gate).
- [ ] T130 Walk through every manual scenario in specs/001-multi-tenant-volunteers/quickstart.md and record any deviations as new tasks.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 approval gates everything. T003 comes before T004–T006.
- **Foundational (Phase 2)**: Depends on Setup. **Blocks every user story.**
- **User Stories (Phases 3–8)**: All depend on Foundational. They can run in priority order
  (P1 → P6), or in parallel where the dependencies below allow.
- **Polish (Phase 9)**: Depends on every story the release includes.

### User Story Dependencies

| Story | Depends on | Notes |
|---|---|---|
| US1 (P1) | Foundational | Creates Invitation, the roster read model and TenantIsolationTest. MVP. |
| US2 (P2) | Foundational | Independent of US1 for its own test. Adds its routes to the TenantIsolationTest sweep once US1 exists. |
| US3 (P3) | Foundational | Independent: `regenerateSignupToken()` is foundational (T017). Extends the US2 listener (T084 after T064) if US2 is done. Otherwise it creates the listener. |
| US4 (P4) | US1 | Extends AcceptInvitation and the invitation acceptance controller (T098, T099). |
| US5 (P5) | US1, US2 | Extends MemberController (US1) and SettingsController (US2). |
| US6 (P6) | US1 | Reuses `IssueInvitation::issue()` and `BuildRosterQuery`. |

### Within Each User Story

1. Tests first, and they must fail.
2. Migrations and models.
3. Notifications and actions.
4. Form Requests.
5. Controllers and routes.
6. Views.
7. The story's test run.

### Parallel Opportunities

- **Phase 1**: T002 runs in parallel with T003.
- **Phase 2**:
  - Tests T007–T011 in parallel.
  - T012 enums in parallel with the migrations.
  - Models T017 and T018 in parallel after T016.
  - Factories T020 and T021 in parallel.
  - Views T025 and T026 in parallel.
- **Each story**: All test tasks marked [P] in parallel, plus notification, form request and
  view tasks marked [P].
- **Across stories**: After Foundational, US1, US2 and US3 can be built by different people at
  the same time. US4 and US6 can start in parallel once US1 is done.

---

## Parallel Example: User Story 1

```bash
# Write all US1 tests together (they must fail first):
Task: "T033 TenantIsolationTest in tests/Feature/TenantIsolationTest.php"
Task: "T034 RosterIndexTest in tests/Feature/Roster/RosterIndexTest.php"
Task: "T035 MemberShowTest in tests/Feature/Roster/MemberShowTest.php"
Task: "T036 IssueInvitationTest in tests/Feature/Invitations/IssueInvitationTest.php"
Task: "T037 ManageInvitationTest in tests/Feature/Invitations/ManageInvitationTest.php"
Task: "T038 AcceptInvitationNewAccountTest in tests/Feature/Invitations/AcceptInvitationNewAccountTest.php"

# Once T040 (Invitation model) exists:
Task: "T041 InvitationFactory in database/factories/InvitationFactory.php"
Task: "T042 InvitationNotification in app/Notifications/InvitationNotification.php"
Task: "T047 Form requests in app/Http/Requests/"
Task: "T051 Roster and invitation views in resources/views/"
```

## Parallel Example: User Story 3

```bash
Task: "T073 RequestOrganizationTest"   Task: "T075 OperatorAccessTest"
Task: "T076 ReviewOrganizationTest"    Task: "T077 SuspendOrganizationTest"
Task: "T080 UniqueOrganizationName rule"   Task: "T081 Organization notifications"
Task: "T088 Operator commands"             Task: "T089 Operator and request views"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 Setup, after the T001 approval.
2. Phase 2 Foundational.
3. Phase 3 US1.
4. **Stop and validate**: run quickstart.md US1 and `TenantIsolationTest`. Seeded organizations
   stand in for US3's request flow.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. US1 → validate → demo (MVP: separate rosters by invitation).
3. US2 → self sign-up with approval.
4. US3 → self-service organization onboarding (operators no longer need the seeder).
5. US4 → multi-organization switching.
6. US5 → lifecycle management.
7. US6 → bulk import and export.

Each increment keeps `TenantIsolationTest` green, so new `orgs.*` routes can't leak.

---

## Notes

- [P] = different files and no dependency on unfinished tasks. Same-file tasks are always
  sequential.
- Commit after each task or logical group. Small, focused commits (Constitution workflow).
- Stop at any checkpoint to validate a story on its own.
- Never skip a failing test to move on. Fix it or raise it (Constitution II).
