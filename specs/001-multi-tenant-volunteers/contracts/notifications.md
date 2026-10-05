# Email Notification Contract

**Feature**: [spec.md](../spec.md) (FR-044) | **Research**: R9, R12

Every notification is queued (`ShouldQueue`) and dispatched `afterCommit()`. No email mentions
any organization other than the one it concerns (FR-006).

| Notification | Trigger | Recipient | Must contain |
|---|---|---|---|
| `VerifyEmail` (framework) | An account is created through self-service (organization request or sign-up link) | The new user | Signed verification link |
| `ResetPassword` (framework) | Forgot-password form submitted | The account's email | Reset link |
| `InvitationNotification` | Invitation issued, resent, or created by import | The invited email, on demand (no account needed) | Organization name, inviter's name, accept link `/invitations/{token}`, expiry date. Wording differs for new and existing accounts ("set up your account" vs "sign in to accept"). |
| `JoinRequestReceived` | Pending membership exists **and** the requester is verified | Every active administrator of that organization | Requester's name and email, link to `orgs.join-requests.index` |
| `JoinRequestApproved` | Administrator approves | The requester | Organization name, link to `orgs.show` |
| `JoinRequestDeclined` | Administrator declines | The requester | Organization name. No reason is required. |
| `OrganizationRequested` | Pending organization exists **and** the requester is verified | Every platform operator | Organization name, requester, link to `operator.organizations.show` |
| `OrganizationApproved` | Operator approves | The requester | Link to `orgs.show` |
| `OrganizationRejected` | Operator rejects | The requester | The operator's reason |
| `OrganizationSuspended` | Operator suspends | Every active administrator of the organization | Statement that access is paused |

**Verification release (R9)**: The `SendPendingRequestNotifications` listener on
`Illuminate\Auth\Events\Verified` sends `JoinRequestReceived` for each of the user's pending
memberships and `OrganizationRequested` for each pending organization they requested.

**Tests**: Every notification above is covered with `Notification::fake()` and
`assertSentTo` / `assertSentOnDemand`. No real email is sent.
