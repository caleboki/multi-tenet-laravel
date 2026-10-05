<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationRejected;
use Tests\TestCase;

class OrganizationRejectedTest extends TestCase
{
    public function test_gives_the_requester_the_operators_reason(): void
    {
        $organization = (new Organization)->forceFill([
            'name' => 'Tool Library',
            'slug' => 'tool-library',
            'rejection_reason' => 'We could not confirm the organization exists.',
        ]);

        $mail = (new OrganizationRejected($organization))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame('Your request for Tool Library', $mail->subject);
        $this->assertStringContainsString('Your request to create Tool Library was not approved.', $content);
        $this->assertStringContainsString('Reason: We could not confirm the organization exists.', $content);
        $this->assertStringNotContainsString(route('orgs.show', $organization), $content);
    }

    public function test_escapes_the_reason_and_organization_name(): void
    {
        $organization = (new Organization)->forceFill([
            'name' => "<script>alert('org')</script>",
            'slug' => 'tool-library',
            'rejection_reason' => "<script>alert('reason')</script>",
        ]);

        $content = (string) (new OrganizationRejected($organization))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
        $this->assertStringNotContainsString("<script>alert('reason')</script>", $content);
    }
}
