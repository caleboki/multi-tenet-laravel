<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationRequested;
use Tests\TestCase;

class OrganizationRequestedTest extends TestCase
{
    public function test_names_the_organization_and_requester_and_links_to_the_review_page(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'Tool Library', 'slug' => 'tool-library', 'contact_email' => 'hello@toollibrary.test']);
        $requester = (new User)->forceFill(['name' => 'Sam Starter', 'email' => 'sam@example.test']);

        $mail = (new OrganizationRequested($organization, $requester))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame('New organization request: Tool Library', $mail->subject);
        $this->assertStringContainsString('Sam Starter (sam@example.test) has asked to create Tool Library.', $content);
        $this->assertStringContainsString('Contact email: hello@toollibrary.test', $content);
        $this->assertStringContainsString(route('operator.organizations.show', $organization), $content);
    }

    public function test_escapes_the_organization_and_requester_names(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'tool-library', 'contact_email' => 'hello@toollibrary.test']);
        $requester = (new User)->forceFill(['name' => "<script>alert('requester')</script>", 'email' => 'sam@example.test']);

        $content = (string) (new OrganizationRequested($organization, $requester))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
        $this->assertStringNotContainsString("<script>alert('requester')</script>", $content);
    }
}
