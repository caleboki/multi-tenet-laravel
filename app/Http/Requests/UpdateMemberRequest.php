<?php

namespace App\Http\Requests;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
     * Administrators may only activate or deactivate. Join requests are reviewed on their
     * own page, and only the member can leave (FR-038).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', 'required_without:status', Rule::enum(MembershipRole::class)],
            'status' => [
                'nullable',
                'required_without:role',
                Rule::enum(MembershipStatus::class)->only([MembershipStatus::Active, MembershipStatus::Inactive]),
            ],
        ];
    }
}
