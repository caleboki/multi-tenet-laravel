<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The partial unique index catches two requests racing past validation with the same name (FR-007, R6).
 */
class OrganizationNameConstraintTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_refuses_two_organizations_whose_names_differ_only_by_case(): void
    {
        Organization::factory()->active()->create(['name' => 'Food Bank North']);

        $this->expectException(UniqueConstraintViolationException::class);

        Organization::factory()->pending()->create(['name' => 'FOOD BANK north']);
    }

    public function test_database_allows_a_new_organization_with_the_name_of_a_rejected_one(): void
    {
        Organization::factory()->rejected()->create(['name' => 'Food Bank North']);

        $organization = Organization::factory()->pending()->create(['name' => 'food bank north']);

        $this->assertModelExists($organization);
    }
}
