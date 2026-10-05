<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;

trait AccountValidationRules
{
    use PasswordValidationRules;

    /**
     * Get the validation rules for the fields a person fills in when creating an account.
     *
     * @return array<string, array<int, Rule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9 +()\-]+$/'],
            'adult_confirmation' => ['accepted'],
        ];
    }

    /**
     * Get the custom validation messages for the account fields.
     *
     * @return array<string, string>
     */
    protected function accountMessages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists. Sign in to continue.',
            'adult_confirmation.accepted' => 'You must confirm you are 18 or older. This platform is for adults only.',
            'phone.regex' => 'The phone number may contain only digits, spaces, and + ( ) -.',
        ];
    }
}
