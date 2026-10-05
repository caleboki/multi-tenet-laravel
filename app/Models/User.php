<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes, matching the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_platform_operator' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'adult_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_operator' => 'boolean',
        ];
    }

    /**
     * Get the user's memberships across all organizations.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the organization the user last worked in.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function lastOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'last_organization_id');
    }

    /**
     * Scope the query to the active administrators of the given organization.
     */
    #[Scope]
    protected function administratorsOf(Builder $query, Organization $organization): void
    {
        $query->whereHas('memberships', fn (Builder $membership) => $membership
            ->whereBelongsTo($organization)
            ->administrators());
    }

    /**
     * Get the user's membership in the given organization, if any.
     *
     * This is deliberately not cached: a cached value would go stale when a
     * membership changes while the same user object is reused across requests.
     */
    public function membershipIn(Organization $organization): ?Membership
    {
        return $this->memberships()->whereBelongsTo($organization)->first();
    }
}
