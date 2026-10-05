<?php

namespace Tests\Feature\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InvitationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accept_allows_the_person_the_invitation_was_sent_to(): void
    {
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $invitation = Invitation::factory()->create(['email' => 'grace@example.test']);

        $result = Gate::forUser($invitee)->inspect('accept', $invitation);

        $this->assertTrue($result->allowed());
    }

    public function test_accept_denies_anyone_else_as_if_the_invitation_did_not_exist(): void
    {
        $someoneElse = User::factory()->create(['email' => 'other@example.test']);
        $invitation = Invitation::factory()->create(['email' => 'grace@example.test']);

        $result = Gate::forUser($someoneElse)->inspect('accept', $invitation);

        $this->assertTrue($result->denied());
        $this->assertSame(404, $result->status());
    }
}
