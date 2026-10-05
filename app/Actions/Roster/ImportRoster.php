<?php

namespace App\Actions\Roster;

use App\Actions\Invitations\IssueInvitation;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class ImportRoster
{
    public function __construct(
        private ParseRosterCsv $parseRosterCsv,
        private IssueInvitation $issueInvitation,
    ) {}

    /**
     * Invite every usable row of an uploaded roster file as a volunteer (FR-046 to FR-049).
     *
     * Rows follow the single-invitation rules. A row is skipped, with its reason, when its
     * name is missing or too long, its email is invalid, it repeats an earlier row's email,
     * or the email is already in this organization's roster. Memberships in other
     * organizations are never considered, so the report can't reveal them. Invitations
     * are created in one transaction, and their emails are queued once it commits.
     *
     * @return array{invited: int, skipped: list<array{row: int, email: ?string, reason: string}>}
     *
     * @throws ValidationException when the whole file is rejected (FR-047).
     */
    public function handle(Organization $organization, User $inviter, string $path): array
    {
        try {
            $rows = $this->parseRosterCsv->handle($path);
        } catch (UnexpectedValueException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $rosterEmails = $this->rosterEmails($organization);
        $firstRowByEmail = [];
        $invitees = [];
        $skipped = [];

        foreach ($rows as $row) {
            $email = Str::lower($row['email']);

            if ($row['name'] === '') {
                $reason = 'Missing name';
            } elseif (mb_strlen($row['name']) > 255) {
                $reason = 'Name too long';
            } elseif (! $this->isValidEmail($email)) {
                $reason = 'Invalid email';
            } elseif (isset($firstRowByEmail[$email])) {
                $reason = "Duplicate of row {$firstRowByEmail[$email]}";
            } else {
                $firstRowByEmail[$email] = $row['row'];
                $reason = isset($rosterEmails[$email]) ? 'Already in roster' : null;
            }

            if ($reason === null) {
                $invitees[] = ['name' => $row['name'], 'email' => $email];
            } else {
                $skipped[] = ['row' => $row['row'], 'email' => $row['email'] === '' ? null : $row['email'], 'reason' => $reason];
            }
        }

        DB::transaction(function () use ($organization, $inviter, $invitees): void {
            foreach ($invitees as $invitee) {
                $this->issueInvitation->issue($organization, $inviter, $invitee['name'], $invitee['email'], MembershipRole::Volunteer);
            }
        });

        return ['invited' => count($invitees), 'skipped' => $skipped];
    }

    /**
     * Load, in one query, every email already in the organization's roster: pending, active
     * and inactive members, and open invitations (FR-034).
     *
     * @return array<string, int>
     */
    private function rosterEmails(Organization $organization): array
    {
        $memberEmails = User::query()
            ->select('email')
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->whereBelongsTo($organization)
                ->whereIn('status', [MembershipStatus::Pending, MembershipStatus::Active, MembershipStatus::Inactive]))
            ->toBase();

        return $organization->invitations()
            ->toBase()
            ->select('email')
            ->union($memberEmails)
            ->pluck('email')
            ->flip()
            ->all();
    }

    /**
     * Apply the same email rules as a single invitation.
     */
    private function isValidEmail(string $email): bool
    {
        return Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->passes();
    }
}
