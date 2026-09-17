<?php

namespace App\Http\Requests\Admin\CommitteeMember;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommitteeMemberRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in CommitteeMemberController
     * via $this->authorize() (CommitteeMemberPolicy), so this stays true
     * to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
