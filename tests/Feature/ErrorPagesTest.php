<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unknown_page_shows_a_not_found_page_with_a_way_home(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSeeText('Page not found');
        $response->assertSee('href="'.url('/').'"', false);
    }

    public function test_signed_in_person_is_pointed_to_their_dashboard_from_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('href="'.route('dashboard').'"', false);
    }

    public function test_not_found_for_another_organization_does_not_reveal_its_name(): void
    {
        $user = User::factory()->create();
        Membership::factory()->for(Organization::factory()->active())->for($user)->administrator()->active()->create();
        $otherOrganization = Organization::factory()->active()->create(['name' => 'Secret Garden Club']);

        $response = $this->actingAs($user)->get(route('orgs.members.index', $otherOrganization));

        $response->assertNotFound();
        $response->assertSeeText('Page not found');
        $response->assertDontSeeText('Secret Garden Club');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errorPages(): array
    {
        return [
            'expired form' => [419, 'This page has expired'],
            'too many attempts' => [429, 'Too many attempts'],
            'server error' => [500, 'Something went wrong'],
        ];
    }

    #[DataProvider('errorPages')]
    public function test_error_page_explains_what_happened(int $status, string $heading): void
    {
        Route::get('/_test/error/{status}', fn (int $status) => abort($status));

        $response = $this->get("/_test/error/{$status}");

        $response->assertStatus($status);
        $response->assertSeeText($heading);
    }

    public function test_unexpected_failure_shows_the_server_error_page_when_debugging_is_off(): void
    {
        Exceptions::fake();
        config(['app.debug' => false]);
        Route::get('/_test/failure', fn () => throw new RuntimeException('Database exploded'));

        $response = $this->get('/_test/failure');

        $response->assertInternalServerError();
        $response->assertSeeText('Something went wrong');
        $response->assertDontSeeText('Database exploded');
    }
}
