<?php

namespace Tests\Feature\Organizations;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_sees_the_sign_up_link(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.settings.edit', $organization));

        $response->assertSee('value="'.route('join.show', $organization->signup_token).'"', false);
    }
}
