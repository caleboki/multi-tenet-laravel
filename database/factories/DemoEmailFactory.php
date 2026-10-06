<?php

namespace Database\Factories;

use App\Models\DemoEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoEmail>
 */
class DemoEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $link = 'http://localhost/invitations/'.fake()->regexify('[A-Za-z0-9]{40}');

        return [
            'recipients' => fake()->safeEmail(),
            'subject' => 'You\'re invited to join '.fake()->company(),
            'text_body' => "You have been invited.\n\nSet up your account: {$link}",
            'html_body' => "<p>You have been invited.</p><a href=\"{$link}\">Set up your account</a>",
        ];
    }
}
