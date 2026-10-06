<?php

namespace App\Listeners;

use App\Models\DemoEmail;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

class StoreDemoEmail
{
    /**
     * Save a copy of every sent email for the demo inbox, when demo mode is on, and
     * keep only the newest ones.
     */
    public function handle(MessageSent $event): void
    {
        if (! config('demo.enabled')) {
            return;
        }

        $message = $event->message;

        DemoEmail::query()->create([
            'recipients' => collect($message->getTo())->map(fn (Address $address): string => $address->getAddress())->implode(', '),
            'subject' => (string) $message->getSubject(),
            'text_body' => is_string($message->getTextBody()) ? $message->getTextBody() : null,
            'html_body' => is_string($message->getHtmlBody()) ? $message->getHtmlBody() : null,
        ]);

        $oldestToKeep = DemoEmail::query()->latest('id')->skip(config('demo.inbox_size') - 1)->value('id');

        if ($oldestToKeep !== null) {
            DemoEmail::query()->where('id', '<', $oldestToKeep)->delete();
        }
    }
}
