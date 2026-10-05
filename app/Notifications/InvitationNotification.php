<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * It carries plain values rather than models, so the email can still be sent if
     * the invitation changes before the queue processes it. It names only the
     * organization the invitation is for (FR-006).
     */
    public function __construct(
        public string $organizationName,
        public string $inviterName,
        public string $token,
        public CarbonInterface $expiresAt,
        public bool $existingAccount,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to join {$this->organizationName}")
            ->line("{$this->inviterName} has invited you to join {$this->organizationName}.")
            ->line($this->existingAccount
                ? 'Sign in with your existing account to accept the invitation.'
                : 'Set up your account to accept the invitation.')
            ->action(
                $this->existingAccount ? 'Sign in to accept' : 'Set up your account',
                route('invitations.show', $this->token),
            )
            ->line("This invitation expires on {$this->expiresAt->format('j M Y')}.");
    }
}
