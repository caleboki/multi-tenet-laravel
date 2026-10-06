<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'role'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
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
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the organization the invitation is for.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the administrator who issued or last resent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /**
     * Scope the query to the unexpired invitations sent to the user's email address, from
     * organizations that are active, so the person can accept them now.
     */
    #[Scope]
    protected function openFor(Builder $query, User $user): void
    {
        $query->where('email', $user->email)
            ->where('expires_at', '>', now())
            ->whereHas('organization', fn (Builder $organization) => $organization->active());
    }

    /**
     * Find the invitation whose link carries the given token.
     *
     * Only a hash of each token is stored, so a leaked database never reveals a working link.
     */
    public static function findByToken(string $plainToken): ?self
    {
        return static::query()->where('token_hash', static::hashToken($plainToken))->first();
    }

    /**
     * Hash a plain token the way it is stored.
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Determine whether the invitation link has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Issue a new link that expires in 7 days and save the invitation, which invalidates any previous link.
     *
     * @return string The plain token to email. It is not stored.
     */
    public function regenerateToken(): string
    {
        $plainToken = Str::random(40);

        $this->token_hash = static::hashToken($plainToken);
        $this->expires_at = now()->addDays(7);
        $this->save();

        return $plainToken;
    }
}
