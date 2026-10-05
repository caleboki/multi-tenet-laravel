# Implementation Plan: Multi-Tenant Organizations & Volunteers

**Branch**: `main` (no feature branch) | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-multi-tenant-volunteers/spec.md`

## Summary

Build the multi-tenant foundation of TrackItFoward. Organizations are requested by anyone and
approved by platform operators. Each organization keeps a separate roster of administrators and
volunteers. One account can belong to many organizations, and the person switches between them.
Volunteers join through an organization's sign-up link, with administrator approval, or by
email invitation, individually or through CSV import. Rosters can be exported as CSV.

**Technical approach**:
- **Tenancy**: a single PostgreSQL database, with `organization_id` on organization-owned
  tables.
- **Current organization**: carried in the URL (`/orgs/{slug}/...`) and enforced by a
  membership middleware plus scoped route bindings, so cross-organization access returns 404.
- **Authentication**: Laravel Fortify, with its single registration flow replaced by three
  purpose-built entry points.
- **Screens**: server-rendered Blade with Tailwind.
- **Emails**: queued notifications sent after commit.

The design decisions are in [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.5 (Sail runtime image `sail-8.5/app`). `composer.json` allows
`^8.3`.

**Primary Dependencies**:
- Laravel Framework 13.34
- **Laravel Fortify ^1.40** (new; approved by the user 2026-10-05; supports Laravel 13 per
  v1.40.0 constraints)
- Tailwind CSS 4 and Vite 8 (existing)
- No JavaScript framework

**Storage**: PostgreSQL 18 (Sail `pgsql` service). The existing `database` drivers handle
queue, sessions and cache.

**Testing**: PHPUnit 12.5, run with `./vendor/bin/sail artisan test`. Tests use
`RefreshDatabase` on the Sail PostgreSQL `testing` database, plus `Notification::fake()` and
`Queue::fake()`.

**Target Platform**: Linux containers (Sail) for development, and a Linux host running Laravel
for production. Users run current desktop and mobile browsers.

**Project Type**: Server-rendered web application (single Laravel project).

**Performance Goals**:
- Roster browse and search under 2 s for 1,000 members (SC-007).
- A 1,000-row import produces its report in under 2 minutes (SC-010).

**Constraints**:
- Zero cross-organization data exposure (SC-001).
- No real network calls in tests.
- Emails queued after commit.
- English only, and accessibility is best effort.
- No audit history, two-step sign-in or SSO (Clarifications).

**Scale/Scope**: 500 organizations and 50,000 memberships (SC-008). About 25 screens, 51
functional requirements and 6 user stories.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Principle | Gate for this feature | Pre-research | Post-design |
|---|---|---|---|---|
| I | Laravel Conventions First | Classes made with `artisan make:*`. Form Requests for all validation, Policies and Gates for authorization, Eloquent relationships for data access, named routes everywhere. No API, so API resources don't apply. | PASS | PASS. The only raw SQL is the partial unique index on organization names, because the schema builder has no partial-index API (R6). It's documented in the migration PHPDoc, as Principle I allows. |
| II | Test-First Verification | Each user story gets feature tests written before code, covering the happy path, validation, and guest / non-member / volunteer / admin boundaries. Bug fixes get regression tests. Factories for all new models. PostgreSQL `testing` database. Fakes for mail and queue. | PASS | PASS. `TenantIsolationTest` sweeps every `orgs.*` route (R15). Unit tests cover CSV parsing and escaping. Factories: Organization, Membership, Invitation, plus a User `operator()` state. |
| III | Enforced Code Quality | Pint before every commit. Typed signatures and PHPDoc array shapes. Constructor promotion. TitleCase enum cases. | PASS | PASS. Enum cases are `Pending`, `Active`, `Administrator` and so on. Import report rows are documented as `array{row: int, email: ?string, reason: string}`. |
| IV | Maintainability & Simplicity | No speculative features. New dependencies and new top-level `app/` folders need approval. Thin controllers with actions. Every migration has a working `down()`. No N+1 queries. Paginated lists. | PASS: Fortify approved. New `app/` folders are listed in Complexity Tracking. | PASS. Fortify and the new top-level `app/` folders were both approved by the user on 2026-10-05 (see Complexity Tracking). No tenancy package, no roles package, no import job or state table. |
| V | Sail-Only Execution | Every documented command uses the `./vendor/bin/sail` prefix. | PASS | PASS. Every command in [quickstart.md](quickstart.md) and [contracts/routes.md](contracts/routes.md) uses Sail. |

| Area | Gate | Status |
|---|---|---|
| Tech stack | PHPUnit, not Pest | PASS |
| Tech stack | Pint default preset | PASS |
| Tech stack | Secrets via `config()` only | PASS |
| Quality gates | Plan, test, format, migration and review gates can be run as written in [quickstart.md](quickstart.md#automated-validation) | PASS |

**Result**: All gates pass. Both deviations below were approved by the user on 2026-10-05. The
new `app/` folders are standard Laravel locations created by `artisan make:*` or
`fortify:install`.

## Project Structure

### Documentation (this feature)

```text
specs/001-multi-tenant-volunteers/
├── spec.md              # Feature specification (with Clarifications)
├── plan.md              # This file
├── research.md          # Phase 0: decisions R1–R15
├── data-model.md        # Phase 1: entities, enums, state transitions, validation
├── quickstart.md        # Phase 1: setup, seeded data, validation scenarios
├── contracts/
│   ├── routes.md        # HTTP routes, middleware, response codes, Artisan commands
│   ├── csv-formats.md   # Import and export file formats, skip reasons
│   └── notifications.md # Email triggers, recipients, contents
├── checklists/
│   └── requirements.md  # Spec quality checklist
└── tasks.md             # Phase 2 output (/speckit-tasks; not created here)
```

### Source Code (repository root)

```text
app/
├── Actions/                         # NEW top-level (created by fortify:install)
│   ├── Fortify/                     # Published by Fortify: ResetUserPassword, UpdateUserPassword,
│   │                                #   UpdateUserProfileInformation (name + phone), PasswordValidationRules.
│   │                                #   CreateNewUser removed (registration disabled).
│   ├── Accounts/CreateAccount.php
│   ├── Organizations/               # RequestOrganization, ApproveOrganization, RejectOrganization,
│   │                                #   SuspendOrganization, ReinstateOrganization
│   ├── Memberships/                 # RequestToJoin, ApproveJoinRequest, DeclineJoinRequest,
│   │                                #   ChangeMembership (role/status/leave + last-admin lock)
│   ├── Invitations/                 # IssueInvitation, ResendInvitation, AcceptInvitation
│   └── Roster/                      # BuildRosterQuery, ParseRosterCsv, ImportRoster, WriteRosterCsv
├── Console/Commands/                # NEW top-level: GrantOperator, RevokeOperator
├── Enums/                           # NEW top-level: OrganizationStatus, MembershipRole,
│                                    #   MembershipStatus, RosterStatus
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php, ProfileController.php
│   │   ├── OrganizationRequestController.php, JoinController.php,
│   │   │   InvitationAcceptanceController.php
│   │   ├── Org/                     # OrganizationHomeController, MembershipController (leave),
│   │   │                            #   MemberController, MemberExportController, JoinRequestController,
│   │   │                            #   InvitationController, RosterImportController,
│   │   │                            #   SettingsController, SignupLinkController
│   │   └── Operator/                # OrganizationController (index/show),
│   │                                #   OrganizationReviewController (approve/reject),
│   │                                #   OrganizationSuspensionController (suspend/reinstate)
│   ├── Middleware/EnsureActiveMembership.php
│   └── Requests/                    # One Form Request per write endpoint, plus RosterFilterRequest
├── Listeners/SendPendingRequestNotifications.php   # NEW top-level
├── Models/                          # User (extended), Organization, Membership, Invitation
├── Notifications/                   # NEW top-level: the 8 app notifications in contracts/notifications.md
├── Policies/                        # NEW top-level: OrganizationPolicy (view, manageMembers). Scoped bindings
│                                    #   already guarantee child records belong to the organization.
├── Providers/
│   ├── AppServiceProvider.php       # Password defaults, rate limiters, operate-platform gate
│   └── FortifyServiceProvider.php   # Published by Fortify: views and limiter wiring
└── Rules/UniqueOrganizationName.php # NEW top-level (artisan make:rule)

config/fortify.php                   # Published; features and lowercase_usernames

database/
├── migrations/                      # create_organizations_table, add_tenancy_columns_to_users_table,
│                                    #   create_memberships_table, create_invitations_table
├── factories/                       # OrganizationFactory, MembershipFactory, InvitationFactory,
│                                    #   UserFactory (+ operator(), adult states)
└── seeders/                         # DatabaseSeeder (quickstart data), RosterPerformanceSeeder (local)

resources/views/
├── layouts/                         # app (header with org switcher), guest
├── components/                      # org-switcher, flash, form inputs, status badge
├── auth/                            # login, forgot-password, reset-password, verify-email
├── dashboard.blade.php, profile/, organization-requests/, join/, invitations/
├── orgs/                            # show, suspended, members/, join-requests/, invitations/,
│                                    #   imports/, settings/
└── operator/organizations/          # index, show

routes/web.php                       # All routes (contracts/routes.md)

tests/
├── Feature/
│   ├── Auth/                        # Fortify flows, adult confirmation, lowercase emails
│   ├── Organizations/               # Request, approve, reject, suspend, name uniqueness (US3)
│   ├── Roster/                      # Roster view, search, filters, export, member update (US1, US5, US6)
│   ├── Invitations/                 # Issue, resend, cancel, accept new/existing account (US1, US4)
│   ├── JoinRequests/                # Sign-up link, verification release, approve/decline (US2, US5)
│   ├── Membership/                  # Switching, last org, leave, last-admin invariant (US4, US5)
│   ├── Import/                      # Whole-file rejection, row skips, report privacy (US6)
│   ├── Operator/                    # Operator gate, no roster access (FR-014)
│   └── TenantIsolationTest.php      # Sweeps all orgs.* routes (SC-001)
└── Unit/
    └── Roster/                      # ParseRosterCsvTest, WriteRosterCsvTest (formula escaping)
```

**Structure Decision**: One Laravel project using the framework's standard directories. Tests
are grouped by area to match the user stories. No new base folders outside `app/`; the new
`app/` folders are listed below for approval.

## Complexity Tracking

| Deviation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| New dependency `laravel/fortify` | Login throttling, password reset, signed email verification and password updates are security-critical flows (R3). | Writing them on framework primitives means about 6 more controllers and their tests to maintain. **The user approved Fortify on 2026-10-05.** |
| New top-level `app/` folders: `Actions`, `Console`, `Enums`, `Listeners`, `Notifications`, `Policies`, `Rules` | Constitution IV asks for actions to keep controllers thin, and these are Laravel's standard locations for policies, notifications, listeners, enums, rules and commands. `fortify:install` creates `app/Actions` itself. | Putting these classes in `app/Models` or `app/Http` would break Laravel conventions (Principle I) and the `make:*` generators. **The user approved these folders on 2026-10-05.** |
