<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'slug' => fn (array $attributes): string => Str::slug($attributes['name']).'-'.Str::lower(Str::random(6)),
            'contact_email' => fake()->unique()->companyEmail(),
            'status' => OrganizationStatus::Pending,
            'requested_by_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the organization is awaiting operator review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Pending,
            'signup_token' => null,
        ]);
    }

    /**
     * Indicate that the organization has been approved.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Active,
            'signup_token' => Str::random(40),
        ]);
    }

    /**
     * Indicate that the organization request was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Rejected,
            'rejection_reason' => 'The organization could not be verified.',
            'signup_token' => null,
        ]);
    }

    /**
     * Indicate that the organization was approved and later suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Suspended,
            'signup_token' => Str::random(40),
        ]);
    }
}
