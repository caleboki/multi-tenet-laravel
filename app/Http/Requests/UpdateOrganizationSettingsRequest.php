<?php

namespace App\Http\Requests;

use App\Rules\UniqueOrganizationName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Administrators are authorized by the `can:manageMembers` middleware on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('contact_email'))) {
            $this->merge(['contact_email' => Str::lower($this->input('contact_email'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The organization may keep its own name, in any letter case (FR-007, FR-008).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', new UniqueOrganizationName($this->route('organization'))],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'self_signup_enabled' => ['required', 'boolean'],
        ];
    }
}
