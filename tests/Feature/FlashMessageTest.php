<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function messageKinds(): array
    {
        return [
            'success' => ['status', 'status'],
            'information' => ['info', 'status'],
            'warning' => ['warning', 'alert'],
        ];
    }

    #[DataProvider('messageKinds')]
    public function test_shows_each_kind_of_message_with_its_role(string $key, string $role): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession([$key => 'A message about your last action.'])->get(route('dashboard'));

        $response->assertSeeText('A message about your last action.');
        $response->assertSee('role="'.$role.'" data-tone="'.$key.'"', false);
    }
}
