<?php

namespace App\Http\Requests;

use App\Actions\Fortify\AccountValidationRules;
use App\Rules\UniqueOrganizationName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOrganizationRequest extends FormRequest
{
    use AccountValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Anyone may request an organization (FR-009).
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
        foreach (['email', 'contact_email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::lower($this->input($field))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A signed-out person also fills in the account fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:120', new UniqueOrganizationName],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255'],
            ...($this->user() === null ? $this->accountRules() : []),
        ];
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
