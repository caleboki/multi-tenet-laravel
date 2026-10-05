<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance for the person who requested the organization.
     */
    public function __construct(public Organization $organization)
    {
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
            ->subject("{$this->organization->name} has been approved")
            ->line("{$this->organization->name} has been approved, and you are its first administrator.")
            ->action("Open {$this->organization->name}", route('orgs.show', $this->organization));
    }
}
