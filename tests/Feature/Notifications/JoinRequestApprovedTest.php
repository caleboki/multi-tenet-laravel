<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestApproved;
use Tests\TestCase;

class JoinRequestApprovedTest extends TestCase
{
    public function test_tells_the_person_they_joined_and_links_to_the_organization(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'Food Bank North', 'slug' => 'food-bank-north']);

        $mail = (new JoinRequestApproved($organization))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame("You've joined Food Bank North", $mail->subject);
        $this->assertStringContainsString('Your request to join Food Bank North has been approved.', $content);
        $this->assertStringContainsString(route('orgs.show', $organization), $content);
    }

    public function test_escapes_the_organization_name(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'food-bank-north']);

        $content = (string) (new JoinRequestApproved($organization))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
    }
}
