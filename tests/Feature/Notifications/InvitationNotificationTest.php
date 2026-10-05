<?php

namespace Tests\Feature\Notifications;

use App\Notifications\InvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvitationNotificationTest extends TestCase
{
    public function test_invitation_to_a_new_email_asks_the_person_to_set_up_an_account(): void
    {
        $notification = $this->invitationNotification(existingAccount: false);

        $mail = $notification->toMail(new AnonymousNotifiable);
        $content = (string) $mail->render();

        $this->assertSame("You're invited to join Food Bank North", $mail->subject);
        $this->assertStringContainsString('Grace Admin has invited you to join Food Bank North.', $content);
        $this->assertStringContainsString('Set up your account', $content);
        $this->assertStringNotContainsString('Sign in to accept', $content);
        $this->assertStringContainsString(route('invitations.show', 'plain-token'), $content);
        $this->assertStringContainsString('This invitation expires on 12 Oct 2026.', $content);
    }

    public function test_invitation_to_an_existing_account_asks_the_person_to_sign_in(): void
    {
        $notification = $this->invitationNotification(existingAccount: true);

        $content = (string) $notification->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('Sign in to accept', $content);
        $this->assertStringNotContainsString('Set up your account', $content);
    }

    public function test_escapes_the_organization_and_inviter_names(): void
    {
        $notification = new InvitationNotification(
            organizationName: "O'Reilly <script>alert('org')</script>",
            inviterName: "<script>alert('inviter')</script>",
            token: 'plain-token',
            expiresAt: Carbon::parse('2026-10-12 12:00:00'),
            existingAccount: false,
        );

        $content = (string) $notification->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('org')</script>", $content);
        $this->assertStringNotContainsString("<script>alert('inviter')</script>", $content);
    }

    private function invitationNotification(bool $existingAccount): InvitationNotification
    {
        return new InvitationNotification(
            organizationName: 'Food Bank North',
            inviterName: 'Grace Admin',
            token: 'plain-token',
            expiresAt: Carbon::parse('2026-10-12 12:00:00'),
            existingAccount: $existingAccount,
        );
    }
}
