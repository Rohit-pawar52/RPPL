<?php

namespace App\Http\Requests\Admin\EditionTransaction;

use App\Models\EditionTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEditionTransactionRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in EditionTransactionController
     * via $this->authorize() (EditionTransactionPolicy), so this stays
     * true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * created_by is deliberately absent here — it is never accepted from
     * the request, only ever set server-side from auth()->id().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'type' => ['required', Rule::in(EditionTransaction::TYPES)],
            'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
