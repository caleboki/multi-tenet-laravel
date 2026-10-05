<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => MembershipRole::Volunteer,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    /**
     * Indicate that the membership grants the Administrator role.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MembershipRole::Administrator,
        ]);
    }

    /**
     * Indicate that the membership grants the Volunteer role.
     */
    public function volunteer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MembershipRole::Volunteer,
        ]);
    }

    /**
     * Indicate that the membership is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }

    /**
     * Indicate that the membership is a join request awaiting approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MembershipStatus::Pending,
            'requested_at' => now(),
            'joined_at' => null,
        ]);
    }

    /**
     * Indicate that an administrator deactivated the membership.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MembershipStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the member left the organization.
     */
    public function left(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MembershipStatus::Left,
        ]);
    }
}
