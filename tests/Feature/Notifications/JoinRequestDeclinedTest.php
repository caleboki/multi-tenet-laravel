<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestDeclined;
use Tests\TestCase;

class JoinRequestDeclinedTest extends TestCase
{
    public function test_tells_the_person_their_request_was_declined_without_linking_to_the_organization(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'Food Bank North', 'slug' => 'food-bank-north']);

        $mail = (new JoinRequestDeclined($organization))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame('Your request to join Food Bank North', $mail->subject);
        $this->assertStringContainsString('Your request to join Food Bank North was declined.', $content);
        $this->assertStringNotContainsString(route('orgs.show', $organization), $content);
    }

    public function test_escapes_the_organization_name(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'food-bank-north']);

        $content = (string) (new JoinRequestDeclined($organization))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
    }
}
