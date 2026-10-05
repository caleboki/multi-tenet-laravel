<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestReceived;
use Tests\TestCase;

class JoinRequestReceivedTest extends TestCase
{
    public function test_names_the_requester_and_links_to_the_join_requests(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'Food Bank North', 'slug' => 'food-bank-north']);
        $requester = (new User)->forceFill(['name' => 'Jo Joiner', 'email' => 'jo@example.test']);
        $administrator = new User;

        $mail = (new JoinRequestReceived($organization, $requester))->toMail($administrator);
        $content = (string) $mail->render();

        $this->assertSame('New request to join Food Bank North', $mail->subject);
        $this->assertStringContainsString('Jo Joiner (jo@example.test) has asked to join Food Bank North.', $content);
        $this->assertStringContainsString(route('orgs.join-requests.index', $organization), $content);
    }

    public function test_escapes_the_requester_and_organization_names(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'food-bank-north']);
        $requester = (new User)->forceFill(['name' => "<script>alert('requester')</script>", 'email' => 'jo@example.test']);

        $content = (string) (new JoinRequestReceived($organization, $requester))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
        $this->assertStringNotContainsString("<script>alert('requester')</script>", $content);
    }
}
