<?php

namespace App\Http\Requests\Admin\CommitteeMember;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommitteeMemberRequest extends FormRequest
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
     * is_active is deliberately absent — a newly created member simply
     * takes the column's DB default (active) without the admin having
     * to choose it explicitly, matching Venue/Team's own convention.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
