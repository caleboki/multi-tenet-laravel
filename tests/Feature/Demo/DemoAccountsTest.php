<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sign_in_page_lists_the_demo_accounts_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get(route('login'));

        $response->assertSeeTextInOrder(['Demo accounts', 'admin@foodbank.test', 'Administrator of Food Bank North', 'operator@example.test', 'Platform operator']);
        $response->assertSeeText('Every demo account uses the password password.');
    }

    public function test_sign_in_page_has_no_demo_accounts_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get(route('login'));

        $response->assertDontSeeText('Demo accounts');
        $response->assertDontSeeText('admin@foodbank.test');
    }

    public function test_one_click_button_signs_in_as_the_demo_account(): void
    {
        config(['demo.enabled' => true]);
        $administrator = User::factory()->create(['email' => 'admin@foodbank.test']);
        $page = $this->get(route('login'));
        $page->assertSee('name="email" value="admin@foodbank.test"', false);

        $response = $this->post(route('login.store'), [
            'email' => 'admin@foodbank.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('start'));
        $this->assertAuthenticatedAs($administrator);
    }
}
