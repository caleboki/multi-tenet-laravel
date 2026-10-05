<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportRosterRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * The file's contents are checked by ParseRosterCsv (FR-047).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The file could not be read. Upload a CSV file.',
            'file.max' => 'The file must be 1 MB or smaller.',
        ];
    }
}
