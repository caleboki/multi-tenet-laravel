<?php

namespace App\Actions\Roster;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\RosterStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BuildRosterQuery
{
    /**
     * Build the organization's roster: its memberships and open invitations as one list (research R5).
     *
     * Both sources start from the organization's relationships, so no other organization's
     * rows can appear. They are combined with UNION ALL inside a subquery, so search,
     * filters, ordering, pagination and the CSV export all work on one result. Join
     * requests from people who haven't verified their email are left out (R9).
     *
     * Each row has `kind` ("membership" or "invitation"), `key`, `name`, `email`, `phone`,
     * `role`, `status` and `joined_at`. The constant `kind` and `status` columns and the
     * NULL placeholders use raw select expressions, because the query builder has no API
     * for selecting a constant.
     */
    public function handle(
        Organization $organization,
        ?string $search = null,
        ?MembershipRole $role = null,
        ?RosterStatus $status = null,
    ): Builder {
        $memberships = $organization->memberships()
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where(fn (EloquentBuilder $query) => $query
                ->whereNot('memberships.status', MembershipStatus::Pending)
                ->orWhereNotNull('users.email_verified_at'))
            ->select([
                DB::raw("'membership' as kind"),
                'memberships.id as key',
                'users.name',
                'users.email',
                'users.phone',
                'memberships.role',
                'memberships.status',
                'memberships.joined_at',
            ])
            ->toBase();

        $invitations = $organization->invitations()
            ->select([
                DB::raw("'invitation' as kind"),
                'invitations.id as key',
                'invitations.name',
                'invitations.email',
                DB::raw('null as phone'),
                'invitations.role',
                DB::raw(sprintf("'%s' as status", RosterStatus::Invited->value)),
                DB::raw('null as joined_at'),
            ])
            ->toBase();

        return DB::query()
            ->fromSub($memberships->unionAll($invitations), 'roster')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.$this->escapeLikeWildcards($search).'%';

                $query->where(fn (Builder $query) => $query
                    ->whereLike('name', $pattern)
                    ->orWhereLike('email', $pattern));
            })
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('kind')
            ->orderBy('key');
    }

    /**
     * Escape the LIKE wildcards in a search term, so "100%" matches only that text.
     */
    private function escapeLikeWildcards(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    }
}
