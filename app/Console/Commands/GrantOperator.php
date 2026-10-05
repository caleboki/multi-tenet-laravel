<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:grant-operator {email : The email address of an existing user}')]
#[Description('Let an existing user operate the platform')]
class GrantOperator extends Command
{
    /**
     * Execute the console command (R13).
     */
    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user has the email address {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_operator' => true])->save();

        $this->info("{$user->email} can now operate the platform.");

        return self::SUCCESS;
    }
}
