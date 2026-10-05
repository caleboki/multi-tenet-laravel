<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationSuspended;
use Tests\TestCase;

class OrganizationSuspendedTest extends TestCase
{
    public function test_tells_administrators_that_access_is_paused(): void
    {
        $organization = (new Organization)->forceFill(['name' => 'River Cleanup', 'slug' => 'river-cleanup']);

        $mail = (new OrganizationSuspended($organization))->toMail(new User);
        $content = (string) $mail->render();

        $this->assertSame('River Cleanup has been suspended', $mail->subject);
        $this->assertStringContainsString('The platform operators have suspended River Cleanup.', $content);
        $this->assertStringContainsString('No one can open it until it is reinstated.', $content);
    }

    public function test_escapes_the_organization_name(): void
    {
        $organization = (new Organization)->forceFill(['name' => "<script>alert('org')</script>", 'slug' => 'river-cleanup']);

        $content = (string) (new OrganizationSuspended($organization))->toMail(new User)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
    }
}
