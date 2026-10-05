# Quickstart & Validation Guide

**Feature**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

This guide shows how to run the feature locally and check each user story end to end. Every
command runs through Sail (Constitution V). Route names refer to
[contracts/routes.md](contracts/routes.md), file formats to
[contracts/csv-formats.md](contracts/csv-formats.md), and emails to
[contracts/notifications.md](contracts/notifications.md).

## Prerequisites

- Docker running, and Sail containers up: `./vendor/bin/sail up -d`
- **APP_URL must match the Sail port.** `.env` currently has `APP_URL=http://localhost:8000`,
  but Sail serves on port 80 unless `APP_PORT` is set. Links in emails (invitations,
  verification, password reset) are built from `APP_URL`. Either:
  - set `APP_URL=http://localhost`, or
  - add `APP_PORT=8000` and restart Sail.

## Setup

```bash
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
./vendor/bin/sail artisan queue:listen     # separate terminal: sends queued emails
```

Emails are written to `storage/logs/laravel.log` (`MAIL_MAILER=log`). To follow them:

```bash
./vendor/bin/sail exec laravel.test tail -f storage/logs/laravel.log
```

### Seeded data (development only, password `password`)

| Account | Purpose |
|---|---|
| `operator@example.test` | Platform operator |
| `admin@foodbank.test` | Administrator of **Food Bank North** |
| `admin@rivercleanup.test` | Administrator of **River Cleanup** |
| `volunteer@foodbank.test` | Volunteer of Food Bank North |
| `both@example.test` | Volunteer of Food Bank North **and** Administrator of River Cleanup |
| `requester@example.test` | Verified requester of the pending organization **Community Kitchen** |

## Automated validation

```bash
./vendor/bin/sail artisan test --compact                                    # full suite
./vendor/bin/sail artisan test --compact --filter=TenantIsolationTest       # SC-001 sweep
./vendor/bin/sail artisan test --compact tests/Feature/Roster               # one area
./vendor/bin/sail pint --dirty --format agent                               # format gate
./vendor/bin/sail artisan migrate:rollback --step=5 && ./vendor/bin/sail artisan migrate
```

**Expected**: all tests pass, Pint reports no changes, and rollback then migrate succeeds.

## Manual scenarios

### US1: Separate roster, built by invitation (P1)

1. **Do**: Sign in as `admin@foodbank.test` and open the roster (`orgs.members.index`).
   **Expect**: only Food Bank North members, including `both@example.test` with no mention of
   River Cleanup.
2. **Do**: Invite `new.person@example.test` as Volunteer.
   **Expect**: a roster row with status **Invited**, and an invitation email in the log.
3. **Do**: Sign out, open the invitation link, set a password and tick the 18+ box.
   **Expect**: you land on the Food Bank North home as a Volunteer, and the roster row is
   **Active**.
4. **Do**: As `admin@foodbank.test`, search the roster for "River".
   **Expect**: no results.
5. **Do**: Copy a member URL from River Cleanup's roster (as `admin@rivercleanup.test`) and open
   it as `admin@foodbank.test`.
   **Expect**: 404.

### US2: Join through the sign-up link (P2)

1. **Do**: As `admin@foodbank.test`, copy the sign-up link from `orgs.settings.edit`.
2. **Do**: Sign out, open the link and register as `joiner@example.test`.
   **Expect**: the dashboard asks you to verify your email, and no admin email has been sent
   yet.
3. **Do**: Open the verification link from the log.
   **Expect**: the dashboard shows "awaiting approval", and the admins receive a
   `JoinRequestReceived` email.
4. **Do**: As admin, open `orgs.join-requests.index` and approve.
   **Expect**: the joiner receives an approval email and can open Food Bank North, but the
   roster URL gives 403.
5. **Do**: As the joiner, change your phone number on `/profile`.
   **Expect**: the admin sees the new number on the member page.

### US3: Organization request and approval (P3)

1. **Do**: Signed out, submit `/organizations/request` with the name "food bank north".
   **Expect**: a "name already taken" error.
2. **Do**: Submit it with the name "Tool Library", then verify the email.
   **Expect**: the operator receives an `OrganizationRequested` email.
3. **Do**: As `operator@example.test`, approve Tool Library.
   **Expect**: the requester's dashboard opens Tool Library as Administrator with an empty
   roster.
4. **Do**: Reject **Community Kitchen** with a reason.
   **Expect**: the requester receives the reason, and a new request named "Community Kitchen"
   is accepted.
5. **Do**: Suspend River Cleanup.
   **Expect**: `admin@rivercleanup.test` sees the suspended page. `both@example.test` can still
   open Food Bank North.

### US4: One person, several organizations (P4)

1. **Do**: Sign in as `both@example.test`.
   **Expect**: you're taken to the last organization used, or asked to choose.
2. **Do**: Use the header switcher to move between the organizations.
   **Expect**: it takes 2 clicks. Roster links appear only in River Cleanup, where you are an
   Administrator.
3. **Do**: As `admin@rivercleanup.test`, invite `volunteer@foodbank.test`.
   **Expect**: the invite email says "sign in to accept", and accepting needs no new password.
4. **Do**: As `admin@foodbank.test`, deactivate `both@example.test`.
   **Expect**: their River Cleanup access is unchanged.

### US5: Status and roles (P5)

1. **Do**: Deactivate `volunteer@foodbank.test`.
   **Expect**: their next request to Food Bank North is refused. Reactivating restores access.
2. **Do**: Promote a volunteer to Administrator.
   **Expect**: they see roster links on their next page load.
3. **Do**: As the only admin of a fresh organization, try to demote yourself or leave.
   **Expect**: refused with the "at least one administrator" message.
4. **Do**: Regenerate the sign-up link.
   **Expect**: the old link shows "no longer valid".
5. **Do**: Turn self sign-up off.
   **Expect**: the link shows "not accepting sign-ups".

### US6: Import and export (P6)

1. **Do**: Download the sample file from `orgs.imports.create`.
2. **Do**: Upload a file with 5 valid rows, 1 invalid email, 1 repeated email,
   `volunteer@foodbank.test`, and `admin@rivercleanup.test`.
   **Expect**: 6 invited (the 5 rows plus the River Cleanup admin, with no mention of River
   Cleanup). 3 skipped: "Invalid email", "Duplicate of row n", "Already in roster".
3. **Do**: Upload a file with 1,001 rows.
   **Expect**: the whole file is rejected and nobody is invited.
4. **Do**: Filter the roster to Active Volunteers and export.
   **Expect**: the CSV has exactly those rows, and a name starting with `=` appears as `'=`.

## Success-criteria spot checks

| Criterion | How to check |
|---|---|
| **SC-001** | `TenantIsolationTest` passes. |
| **SC-007** | Seed an organization with 1,000 members (`RosterPerformanceSeeder`, local only), load the roster and search. Pages should render in under 2 seconds. |
| **SC-010** | Import a 1,000-row file. The report should appear in under 2 minutes. Emails go out afterwards through the queue. |
