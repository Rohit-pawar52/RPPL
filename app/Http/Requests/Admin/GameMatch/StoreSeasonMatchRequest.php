<?php

namespace App\Http\Requests\Admin\GameMatch;

use App\Models\Edition;

/**
 * Scheduling a match from inside a season. Same rules as the standalone
 * StoreGameMatchRequest, but the season comes from the URL, never from the
 * form: whatever edition_id was posted is overwritten before validation, so
 * the team-belongs-to-season and match-number-unique-per-season checks always
 * run against the season being viewed.
 *
 * A completed season is refused by the controller (with a clear message)
 * rather than as a validation error on a field the admin cannot see, so the
 * "not completed" rule on edition_id is dropped here.
 */
class StoreSeasonMatchRequest extends StoreGameMatchRequest
{
    protected function prepareForValidation(): void
    {
        /** @var Edition $edition */
        $edition = $this->route('edition');

        $this->merge(['edition_id' => $edition->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['edition_id' => ['required', 'integer']] + parent::rules();
    }
}
