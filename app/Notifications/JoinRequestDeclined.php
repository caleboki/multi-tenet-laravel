<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JoinRequestDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance for the person whose request was declined.
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
     *
     * No reason is given (FR-028). The person may ask again later through the sign-up link.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your request to join {$this->organization->name}")
            ->line("Your request to join {$this->organization->name} was declined.")
            ->line("If you think this was a mistake, you can ask again using the organization's sign-up link.");
    }
}
