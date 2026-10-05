<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    use AccountValidationRules;

    /**
     * Validate and update the user's name and phone number (FR-043).
     *
     * The email address is ignored, because changing it is out of scope.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make(
            $input,
            Arr::only($this->accountRules(), ['name', 'phone']),
            $this->accountMessages(),
        )->validateWithBag('updateProfileInformation');

        $user->fill($validated)->save();
    }
}
