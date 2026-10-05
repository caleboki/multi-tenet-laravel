<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAccount
{
    /**
     * Create an account for one of the three entry points: an organization request,
     * an organization sign-up link, or an accepted invitation.
     *
     * Accounts created from an invitation are already verified, because the link was
     * delivered to the address. Every other account must verify its email address. The
     * Registered event, which sends the verification email, fires only once any
     * surrounding transaction commits, so a rolled-back sign-up sends nothing.
     *
     * @param  array{name: string, email: string, password: string, phone?: ?string}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, bool $adultConfirmed, bool $verified): User
    {
        if (! $adultConfirmed) {
            throw ValidationException::withMessages([
                'adult_confirmation' => 'You must confirm you are 18 or older. This platform is for adults only.',
            ]);
        }

        $user = User::make([
            'name' => $attributes['name'],
            'email' => Str::lower(trim($attributes['email'])),
            'password' => $attributes['password'],
            'phone' => $attributes['phone'] ?? null,
        ]);

        $user->forceFill([
            'adult_confirmed_at' => now(),
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        if (! $verified) {
            DB::afterCommit(fn () => event(new Registered($user)));
        }

        return $user;
    }
}
