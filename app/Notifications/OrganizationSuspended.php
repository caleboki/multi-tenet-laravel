<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance for one of the organization's active administrators.
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
            ->subject("{$this->organization->name} has been suspended")
            ->line("The platform operators have suspended {$this->organization->name}.")
            ->line('No one can open it until it is reinstated.');
    }
}
