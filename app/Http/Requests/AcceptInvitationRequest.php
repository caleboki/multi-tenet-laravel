<?php

namespace App\Http\Requests;

use App\Actions\Fortify\AccountValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class AcceptInvitationRequest extends FormRequest
{
    use AccountValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Anyone holding the invitation link may accept it. The controller checks the token.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only a signed-out person creates an account, and the email address comes from the
     * invitation, so it is not a field. A signed-in invitee submits no fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->user() === null ? Arr::except($this->accountRules(), 'email') : [];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->accountMessages();
    }
}
