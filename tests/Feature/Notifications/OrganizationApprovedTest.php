<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationApproved;
use Tests\TestCase;

class OrganizationApprovedTest extends TestCase
{
    public function test_tells_the_requester_they_are_the_first_administrator_and_links_to_the_organization(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'Tool Library', 'slug' => 'tool-library']);

        $mail = (new OrganizationApproved($organization))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame('Tool Library has been approved', $mail->subject);
        $this->assertStringContainsString('Tool Library has been approved, and you are its first administrator.', $content);
        $this->assertStringContainsString(route('orgs.show', $organization), $content);
    }

    public function test_escapes_the_organization_name(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'tool-library']);

        $content = (string) (new OrganizationApproved($organization))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
    }
}
