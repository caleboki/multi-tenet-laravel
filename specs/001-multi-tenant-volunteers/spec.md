# Feature Specification: Multi-Tenant Organizations & Volunteers

**Feature Branch**: `main` (no feature branch created)

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Build a multi-tenant application with organizations and volunteer users. Different organizations would have different volunteer users."

## Clarifications

### Session 2026-10-05

- Q: Can one person belong to more than one organization? → A: Yes. One account can be a member
  of many organizations, with a separate role and status in each, and the person switches between
  them.
- Q: Who can create a new organization? → A: Anyone can request one through self-service sign-up.
  The organization stays pending until a platform operator approves it.
- Q: How does a volunteer join an organization? → A: Through an organization-specific sign-up link,
  after which an administrator approves the request. Administrators can also invite volunteers
  directly by email.
- Q: Should the system keep a history of who changed what (approvals, role changes, suspensions)?
  → A: No history in v1. Only the current state of organizations and memberships is stored.
- Q: Can people under 18 sign up as volunteers? → A: No. Adults only: everyone confirms they are
  18 or older when creating an account. No birth date is stored.
- Q: Do administrators need to bulk-import a volunteer list or export their roster? → A: Both.
  Admins can upload a CSV file to invite many volunteers at once and download the roster as CSV.
- Q: Should operators and administrators be required to use two-step sign-in? → A: Not in v1.
  All accounts sign in with email and password only.
- Q: Should every screen meet a formal accessibility standard? → A: Best effort, with no formal
  target. Screens follow good accessibility practice but are not tested against a standard.
- Q: What happens when a demoted administrator's next roster action arrives? → A: It is refused
  with an "access denied" page linking back to the organization's home (decided during analysis).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Administrator builds an isolated volunteer roster (Priority: P1)

An organization administrator signs in and sees their organization's roster. They invite
volunteers by email, and each invited person sets up their account (or uses their existing one)
to join. The administrator searches and filters the roster and views each member's details. They
only ever see members of their own organization.

**Why this priority**: Keeping each organization's volunteers separate is the core promise of the
product. Without a working, isolated roster, there is nothing else to build on.

**Independent Test**: Prepare two active organizations, each with one administrator. Each
administrator invites volunteers who then accept. Confirm each administrator sees, searches, and
opens only their own organization's members, and that direct links to the other organization's
members are refused.

**Acceptance Scenarios**:

1. **Given** an administrator of Organization A is signed in, **When** they open the roster,
   **Then** they see every member of Organization A and no members of any other organization.
2. **Given** an administrator of Organization A, **When** they invite a volunteer by name and
   email address, **Then** that person appears in Organization A's roster as "invited" and receives
   an email with a one-time setup link.
3. **Given** a person without an account receives an invitation, **When** they open the link and
   set a password, **Then** their account is created and they become an active volunteer of
   Organization A.
4. **Given** an administrator of Organization A, **When** they search the roster for a name that
   exists only in Organization B, **Then** no results are returned.
5. **Given** an administrator of Organization A, **When** they open a direct link to a member of
   Organization B, **Then** access is refused with a "not found" response that does not reveal
   whether the member exists.

---

### User Story 2 - Volunteer requests to join through the organization's sign-up link (Priority: P2)

A person opens an organization's sign-up link, creates an account (or signs in with an existing
one), and requests to join. The organization's administrators are notified, review the request,
and approve or decline it. Once approved, the volunteer can sign in, see the organization, and
keep their own profile up to date. They cannot see the roster or any other member's details.

**Why this priority**: Self sign-up is how most volunteers will arrive. It removes the
administrator's data-entry burden while keeping them in control of who joins.

**Independent Test**: With one active organization and an administrator in place, open the
organization's sign-up link as a new person, register, verify the email address, and request to
join. Approve the request as the administrator, then confirm the volunteer can sign in, edit
their own profile, and cannot reach the roster.

**Acceptance Scenarios**:

1. **Given** a person opens Organization A's sign-up link, **When** they register and verify their
   email address, **Then** a join request is created with status "pending approval" and
   Organization A's administrators are notified.
2. **Given** a person with a pending join request, **When** they sign in, **Then** they see that
   their request to Organization A is awaiting approval and cannot access Organization A.
3. **Given** a pending join request, **When** an administrator approves it, **Then** the person
   becomes an active volunteer of Organization A and is notified by email.
4. **Given** a pending join request, **When** an administrator declines it, **Then** the person is
   notified, does not gain access, and the request leaves the pending list.
5. **Given** a signed-in volunteer, **When** they try to open the roster or another member's
   profile, **Then** access is refused.
6. **Given** a signed-in volunteer, **When** they update their name or phone number, **Then** the
   change is saved and shown to the administrators of every organization they belong to.

---

### User Story 3 - A new organization is requested and approved (Priority: P3)

A person registers a new organization on the platform. The organization stays pending while a
platform operator reviews it. Once the operator approves it, the requester becomes its first
administrator and can start building the roster. A platform operator can also reject a request
or later suspend an organization.

**Why this priority**: The platform needs a controlled way to bring on new organizations. For
early testing, organizations can be prepared ahead of time, so this follows the roster and
volunteer journeys.

**Independent Test**: Submit a new organization request, sign in as a platform operator to
approve it, then sign in as the requester and confirm they are the organization's administrator
with an empty roster.

**Acceptance Scenarios**:

1. **Given** a person submits an organization request with a unique name, **When** they verify
   their email address, **Then** the organization is created with status "pending" and platform
   operators are notified.
2. **Given** a pending organization, **When** the requester signs in, **Then** they see that the
   organization is awaiting approval and cannot invite or approve volunteers yet.
3. **Given** a pending organization, **When** a platform operator approves it, **Then** it becomes
   active, the requester becomes its first administrator, and the requester is notified by email.
4. **Given** a pending organization, **When** a platform operator rejects it with a reason,
   **Then** the requester is notified with that reason, and the organization name becomes
   available again.
5. **Given** an existing or pending organization named "Food Bank North", **When** someone
   requests another organization named "food bank north", **Then** the request is refused because
   the name is already taken.
6. **Given** an active organization, **When** a platform operator suspends it, **Then** none of
   its members can access it until the operator reinstates it.

---

### User Story 4 - A person belongs to several organizations (Priority: P4)

A person who volunteers for more than one organization uses a single account. They pick which
organization to work in after signing in and can switch at any time. Their role and status are
separate in each organization, and no organization can see which other organizations they
belong to.

**Why this priority**: Many volunteers help more than one group. Supporting this with one
account avoids duplicate sign-ups and passwords, but the platform is usable with single-org
members first.

**Independent Test**: Make one person an active volunteer of Organization A and an administrator
of Organization B. Sign in, switch between the two, and confirm the person sees only the current
organization's data with the correct role in each, and that each organization's administrators
see no trace of the other membership.

**Acceptance Scenarios**:

1. **Given** a person with active memberships in Organizations A and B, **When** they sign in,
   **Then** they are asked which organization to open, or taken to the one they used last.
2. **Given** a person working in Organization A, **When** they switch to Organization B, **Then**
   every page shows only Organization B's data and reflects their role in Organization B.
3. **Given** a person who is already an active volunteer in Organization A, **When** Organization
   B invites them by the same email address, **Then** they accept by signing in with their
   existing account and gain Organization B's membership without creating a new password.
4. **Given** a person who belongs to Organizations A and B, **When** an administrator of
   Organization A views their details, **Then** nothing indicates the person's membership in
   Organization B.
5. **Given** a person who belongs to Organizations A and B, **When** Organization A deactivates
   them, **Then** their access to Organization B is unchanged.

---

### User Story 5 - Administrator manages member status and roles (Priority: P5)

An administrator deactivates a volunteer who has left, reactivates a returning volunteer, and
promotes a trusted volunteer to administrator so the workload can be shared. Administrators also
manage the organization's sign-up link. The organization can never be left without an
administrator.

**Why this priority**: Rosters change over time, but organizations can operate for a while with
only inviting and approving. Lifecycle management can follow once the basics work.

**Independent Test**: In one organization, deactivate a volunteer and confirm they can no longer
access it; reactivate them and confirm access returns; promote a volunteer to administrator and
confirm they gain roster access; try to demote the only administrator and confirm it is blocked;
regenerate the sign-up link and confirm the old one stops working.

**Acceptance Scenarios**:

1. **Given** an active volunteer, **When** an administrator deactivates them, **Then** the
   volunteer can no longer access the organization, and their record stays in the roster marked
   as inactive.
2. **Given** an inactive volunteer, **When** an administrator reactivates them, **Then** the
   volunteer can access the organization again.
3. **Given** a volunteer, **When** an administrator promotes them to administrator, **Then** they
   can manage the roster on their next page load.
4. **Given** an organization with exactly one active administrator, **When** that administrator
   tries to demote, deactivate, or leave, **Then** the action is refused with an
   explanation that the organization must keep at least one administrator.
5. **Given** an organization's sign-up link, **When** an administrator regenerates it, **Then**
   the old link stops working and the new link accepts join requests.
6. **Given** an administrator turns off self sign-up, **When** someone opens the sign-up link,
   **Then** they are told the organization is not accepting sign-ups.

---

### User Story 6 - Administrator imports and exports the roster (Priority: P6)

An administrator moving from a spreadsheet uploads a file of names and email addresses, and
everyone in it is invited in one step. Afterwards they see which rows were invited and which were
skipped, and why. Administrators can also download their roster as a spreadsheet file at any
time.

**Why this priority**: Bulk import removes the main barrier for organizations that already have
large volunteer lists. It builds on invitations (User Story 1), so it comes after the core
journeys.

**Independent Test**: In one active organization, upload a file containing valid rows, a
duplicate email, an invalid email, and an email already in the roster. Confirm valid rows are
invited, every other row is reported as skipped with its reason, and the downloaded roster file
contains exactly the organization's members.

**Acceptance Scenarios**:

1. **Given** an administrator uploads a valid file of 200 names and email addresses, **When** the
   import finishes, **Then** 200 people are invited as Volunteers and the report shows 200
   invited and 0 skipped.
2. **Given** a file where one row has an invalid email and another repeats an earlier row's
   email, **When** it is imported, **Then** those 2 rows are skipped and the report lists each one
   by row number with its reason, while all other rows are invited.
3. **Given** a file that includes an email already invited, pending, active, or inactive in the
   organization, **When** it is imported, **Then** that row is skipped as "already in roster".
4. **Given** a file that includes the email of someone who belongs only to another organization,
   **When** it is imported, **Then** that person is invited like anyone else, and the report
   gives no sign that they belong to another organization.
5. **Given** an administrator has filtered the roster to active Volunteers, **When** they
   download the roster, **Then** the file contains exactly the members shown by that filter, from
   their organization only.

---

### Edge Cases

- An administrator invites an email address that already has an active, invited, or pending
  membership in their organization: the invitation is refused and the administrator is pointed to
  the existing roster entry.
- A person who is already an active member opens their organization's sign-up link: they are told
  they are already a member and taken to the organization.
- An inactive member opens the sign-up link: no new request is created, and they are told to
  contact the organization's administrator.
- A person whose join request was declined opens the sign-up link again: they can submit a new
  request.
- A person self-registers but never verifies their email address: no join or organization request
  reaches administrators or operators until the address is verified.
- A person signs in with no active memberships: they see the status of any pending invitations,
  join requests, or organization requests, and nothing else.
- A person's only organization is suspended: on sign-in they are told the organization is
  suspended. If they belong to other organizations, those remain available.
- An administrator is demoted while they have the roster open: their next roster action is
  refused with an "access denied" page that links back to the organization's home.
- Two administrators change the same member's role or status at the same time: the later change
  wins, and the roster shows the current values.
- A setup or invitation link is used after it expires or after it has already been used: setup is
  refused and the person is told to ask the administrator for a new invitation.
- A volunteer opens a sign-up link that was regenerated: they are told the link is no longer
  valid.
- A person creating an account does not confirm they are 18 or older: the account is not
  created, and they are told the platform is for adults only.
- The requester of a rejected organization still has an account: they keep any other memberships
  and can submit a new organization request.

## Requirements *(mandatory)*

### Functional Requirements

**Organizations and data isolation**

- **FR-001**: System MUST support any number of organizations, each with its own separate set of
  members.
- **FR-002**: System MUST associate every piece of organization data (memberships, join requests,
  invitations, and any future volunteer activity) with exactly one organization.
- **FR-003**: System MUST show a signed-in user only the data of the organization they are
  currently working in, and only if they hold an active membership in it.
- **FR-004**: System MUST refuse any attempt to view or change another organization's data,
  including through direct links, and MUST respond as "not found" without revealing whether the
  record exists.
- **FR-005**: Lists, searches, and counts MUST include only the current organization's records.
- **FR-006**: System MUST NOT reveal to one organization which other organizations a person
  belongs to.
- **FR-007**: Each organization MUST have a name that is unique across all pending, active, and
  suspended organizations, compared without regard to letter case, and MAY have a contact email.
- **FR-008**: Administrators MUST be able to update their organization's name and contact email.

**Organization requests and platform operators**

- **FR-009**: Anyone MUST be able to request a new organization by providing an organization name
  and contact email, and either creating an account or signing in with an existing one.
- **FR-010**: A requested organization MUST remain "pending" until a platform operator approves
  it. Pending organizations MUST NOT accept invitations or join requests.
- **FR-011**: Platform operators MUST be able to view all organization requests and organizations
  with their name, status, contact email, requester, creation date, and active member count.
- **FR-012**: Platform operators MUST be able to approve a pending organization, which makes the
  requester its first administrator, or reject it with a reason that is sent to the requester.
- **FR-013**: Platform operators MUST be able to suspend an active organization and reinstate a
  suspended one. Members of a suspended organization MUST NOT be able to access it.
- **FR-014**: Platform operators MUST NOT be able to view organization rosters or member details
  through their operator role.

**Roles and permissions**

- **FR-015**: System MUST support two roles within an organization: Administrator and Volunteer.
  A person's role is set separately for each organization they belong to.
- **FR-016**: Administrators MUST be able to view and manage their organization's roster, join
  requests, sign-up link, and settings. Volunteers MUST only be able to view the organization's
  name and view and edit their own profile.
- **FR-017**: Administrators MUST be able to promote a volunteer to administrator and demote an
  administrator to volunteer.
- **FR-018**: System MUST ensure every active organization always has at least one active
  administrator, refusing any demotion, deactivation, or departure that would leave none.

**Membership and switching**

- **FR-019**: A person MUST have a single account, identified by a unique email address, that can
  hold memberships in any number of organizations.
- **FR-020**: Each membership MUST have its own role and status (invited, pending approval,
  active, inactive, or left). Changing a membership in one organization MUST NOT affect the
  person's memberships in other organizations.
- **FR-021**: A person with more than one active membership MUST be able to choose which
  organization to work in after signing in and switch organizations from any page.
- **FR-022**: After sign-in, System MUST open the organization the person last worked in, or ask
  them to choose if there is no previous choice or it is no longer available.
- **FR-023**: System MUST show the signed-in person which organization they are currently working
  in on every page.
- **FR-024**: Members MUST be able to leave an organization, unless they are its last active
  administrator. Leaving MUST end their access and set their status to "left". A person who left
  MAY rejoin later through the sign-up link or an invitation.

**Joining through the sign-up link**

- **FR-025**: Each active organization MUST have a unique sign-up link that shows the
  organization's name and lets a person request to join.
- **FR-026**: A person using the sign-up link MUST either create an account or sign in with an
  existing one. The request MUST be created with status "pending approval".
- **FR-027**: Administrators MUST be able to view pending join requests with each person's name,
  email, and request date, and approve or decline each one.
- **FR-028**: Approving a join request MUST make the person an active Volunteer. Declining MUST
  remove the request from the roster and the pending list without granting access. The person
  MAY request again later.
- **FR-029**: Administrators MUST be able to regenerate the sign-up link, which immediately
  invalidates the previous link, and turn self sign-up off and on.

**Joining by invitation**

- **FR-030**: Administrators MUST be able to invite a person by providing a name and email
  address, and choose whether they join as Administrator or Volunteer.
- **FR-031**: An invitation MUST send a one-time link that expires 7 days after it is issued.
  Accepting it MUST create the account if the email address is new, or require sign-in with the
  existing account if it is not.
- **FR-032**: Accepting an invitation MUST make the membership active without further approval.
- **FR-033**: Administrators MUST be able to cancel an unused invitation and resend an invitation,
  which issues a new link and invalidates the old one.
- **FR-034**: System MUST refuse an invitation or join request for an email address that already
  has an invited, pending approval, active, or inactive membership in the same organization.
  Inactive members are restored only by an administrator reactivating them (FR-038).

**Roster management**

- **FR-035**: Administrators MUST be able to view their roster showing each member's name, email,
  role, status, and date joined.
- **FR-036**: Administrators MUST be able to search the roster by name or email and filter it by
  role and status.
- **FR-037**: Administrators MUST be able to view a member's name, email, phone number, role,
  status, and date joined. Personal details are owned by the member and MUST NOT be editable by
  administrators.
- **FR-038**: Administrators MUST be able to deactivate and reactivate members. Deactivation MUST
  remove the member's access to the organization while keeping the roster record.
- **FR-039**: Rosters and join request lists MUST be shown in pages so that large lists stay quick
  to browse.

**Accounts, self-service, and notifications**

- **FR-040**: Users MUST sign in with an email address and password, and MUST be able to sign out.
- **FR-041**: Users who create an account through self-service MUST verify their email address
  before any join request or organization request is submitted for review.
- **FR-042**: Users MUST be able to reset a forgotten password through a link sent to their email
  address.
- **FR-043**: Users MUST be able to view and edit their own name, phone number, and password.
- **FR-044**: System MUST send email notifications for: invitations; email verification; password
  reset; new join requests (to the organization's administrators); join request approval or
  decline (to the person); new organization requests (to platform operators); and organization
  approval, rejection, or suspension (to the requester or administrators).
- **FR-045**: Every account creation (self sign-up, invitation acceptance, or organization
  request) MUST require the person to confirm they are 18 or older. Without that confirmation,
  the account MUST NOT be created.

**Roster import and export**

- **FR-046**: Administrators MUST be able to upload a CSV file with a name column and an email
  column to invite many people at once. Every valid row MUST be invited as a Volunteer, following
  the same rules as a single invitation (FR-030 to FR-034).
- **FR-047**: An import file MUST contain at most 1,000 rows. A file that exceeds the limit,
  cannot be read, or lacks the name or email column MUST be rejected as a whole, with an
  explanation and no invitations sent.
- **FR-048**: Within an accepted file, System MUST skip any row with a missing name, an invalid
  email, an email repeated from an earlier row, or an email already in the roster (FR-034). Every
  other row MUST be invited.
- **FR-049**: When an import finishes, the administrator MUST see a report with the number of
  people invited and skipped, and for each skipped row, its row number and reason. The report
  MUST NOT reveal whether anyone belongs to another organization.
- **FR-050**: Administrators MUST be able to download a sample import file showing the expected
  columns.
- **FR-051**: Administrators MUST be able to download their roster as a CSV file containing each
  member's name, email, phone number, role, status, and date joined. The file MUST contain only
  the members matching the roster's current search and filters, and only from the current
  organization.

### Key Entities *(include if feature involves data)*

- **Organization**: A group that uses the platform to manage its volunteers. Has a unique name, an
  optional contact email, a status (pending, active, rejected, or suspended), a rejection reason
  when rejected, a sign-up link, and a setting for whether self sign-up is on. Owns its
  memberships, join requests, and invitations.
- **User**: A person who can sign in. Has a name, a unique email address, an optional phone
  number, a password, whether their email address is verified, and when they confirmed they are
  18 or older. A User may also be a platform operator.
- **Membership**: Connects one User to one Organization. Records the role (Administrator or
  Volunteer), status (invited, pending approval, active, inactive, or left), date requested or
  invited, and date joined. A User's access to an Organization is determined entirely by this
  record.
- **Invitation**: A one-time link sent to an email address for a specific Organization and role.
  Records who issued it, when it expires, and whether it was used or cancelled.
- **Platform Operator**: A User who reviews organization requests and can suspend or reinstate
  organizations. Has no access to organization rosters through this role.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In acceptance testing across every screen, list, search, direct link, and
  notification, users of one organization see zero records belonging to another organization.
- **SC-002**: An administrator can invite a new volunteer in under 1 minute.
- **SC-003**: An invited volunteer can go from receiving the invitation email to being signed in
  within 3 minutes.
- **SC-004**: A person can submit a join request through a sign-up link, including account
  creation and email verification, within 3 minutes.
- **SC-005**: A person can submit an organization request within 5 minutes, and the first
  administrator can invite volunteers as soon as the organization is approved.
- **SC-006**: A person who belongs to several organizations can switch from one to another in no
  more than 2 actions.
- **SC-007**: For a roster of 1,000 members, browsing and search results appear within 2 seconds.
- **SC-008**: The platform supports at least 500 organizations and 50,000 memberships in total
  while still meeting SC-007.
- **SC-009**: At least 90% of volunteers complete account setup or a join request on their first
  attempt without contacting an administrator.
- **SC-010**: An administrator can import a file of 1,000 volunteers and see the completed import
  report within 2 minutes of uploading it.

## Assumptions

- This feature establishes the organization and volunteer foundation only. Volunteer activity
  features such as shift scheduling, event sign-up, and hour tracking are out of scope and will be
  specified separately on top of this foundation.
- Platform operator accounts are set up when the platform is deployed. Adding and removing
  operators from within the product is out of scope for this version.
- Sign-in uses email and password with email-based password reset, for every account including
  platform operators and administrators. Two-step sign-in, single sign-on, and social login are
  out of scope for this version.
- Users cannot change their email address in this version.
- The product is a web application used in a desktop or mobile browser. Native mobile apps are
  out of scope.
- Email can be sent for every notification listed in FR-044.
- Public request forms (organization requests and sign-up links) are protected against automated
  abuse using the platform's standard measures.
- Deleting organizations, billing or subscriptions, and custom branding or domains per
  organization are out of scope.
- No audit history is kept in this version. The system stores only the current state of each
  organization and membership, not a record of who changed it or when.
- Members are deactivated, not deleted, so their roster entry is kept. Permanent deletion of
  personal data on request will be handled in a later feature.
- The volunteer profile is limited to name, email, and phone number in this version.
- The platform is for adults only. Age is self-declared and not verified. Support for volunteers
  under 18, including parental consent, is out of scope.
- The interface is in English only.
- Accessibility is best effort: screens follow good practice such as keyboard use, labelled form
  fields, and readable contrast, but no formal standard is targeted or tested in this version.
