<?php

namespace App\Http\Requests;

use App\Enums\MembershipRole;
use App\Enums\RosterStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterFilterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(MembershipRole::class)],
            'status' => ['nullable', Rule::enum(RosterStatus::class)],
        ];
    }
}
