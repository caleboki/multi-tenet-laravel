<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationRejected extends Notification implements ShouldQueue
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
     * Get the mail representation of the notification, including the operator's reason (FR-012).
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your request for {$this->organization->name}")
            ->line("Your request to create {$this->organization->name} was not approved.")
            ->line("Reason: {$this->organization->rejection_reason}");
    }
}
