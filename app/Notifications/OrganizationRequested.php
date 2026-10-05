<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance for one of the platform operators.
     */
    public function __construct(
        public Organization $organization,
        public User $requester,
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
            ->subject("New organization request: {$this->organization->name}")
            ->line("{$this->requester->name} ({$this->requester->email}) has asked to create {$this->organization->name}.")
            ->line("Contact email: {$this->organization->contact_email}")
            ->action('Review the request', route('operator.organizations.show', $this->organization));
    }
}
