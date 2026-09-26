<?php

namespace App\Http\Requests\Admin\DataCleanup;

use App\Http\Requests\Admin\DataCleanup\Concerns\ValidatesCutoffDate;
use Illuminate\Foundation\Http\FormRequest;

class DeleteFailedJobsRequest extends FormRequest
{
    use ValidatesCutoffDate;

    /**
     * Authorization is handled explicitly in DataCleanupController via
     * $this->authorize('manage-tournament'), so this stays true to avoid
     * duplicating that check.
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
            'before_date' => ['required', 'date', $this->rejectFutureCutoffDate(...)],
        ];
    }
}
