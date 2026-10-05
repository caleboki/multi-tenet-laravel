<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
            'requested_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * Get the organization the membership belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user the membership belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to active memberships.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    /**
     * Scope the query to active administrators.
     */
    #[Scope]
    protected function administrators(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active)
            ->where('role', MembershipRole::Administrator);
    }

    /**
     * Scope the query to join requests that are ready for review: pending, with a verified user.
     */
    #[Scope]
    protected function awaitingApproval(Builder $query): void
    {
        $query->where('status', MembershipStatus::Pending)
            ->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'));
    }

    /**
     * Determine whether the membership grants the Administrator role.
     */
    public function isAdministrator(): bool
    {
        return $this->role === MembershipRole::Administrator;
    }

    /**
     * Determine whether the membership is active.
     */
    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }
}
