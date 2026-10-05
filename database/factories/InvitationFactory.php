<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->active(),
            'invited_by_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => MembershipRole::Volunteer,
            'token_hash' => Invitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * Indicate that the invitation grants the Administrator role.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MembershipRole::Administrator,
        ]);
    }

    /**
     * Indicate that the invitation link has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Store the hash of a known plain token, so a test can open the invitation link.
     */
    public function withToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => Invitation::hashToken($plainToken),
        ]);
    }
}
