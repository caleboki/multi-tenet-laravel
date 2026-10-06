<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_sees_what_the_app_is_with_sign_in_and_request_links(): void
    {
        $response = $this->get('/');

        $response->assertSeeText(config('app.name'));
        $response->assertSeeText('Keep each organization’s volunteers in one place');
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('organization-requests.create').'"', false);
        $response->assertDontSeeText('Laravel has an incredibly rich ecosystem');
    }

    public function test_signed_in_person_is_offered_their_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('href="'.route('dashboard').'"', false);
        $response->assertSeeText('Go to your dashboard');
        $response->assertDontSee('href="'.route('login').'"', false);
    }
}
