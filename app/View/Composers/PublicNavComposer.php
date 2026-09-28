<?php

namespace App\View\Composers;

use App\Models\Edition;
use Illuminate\View\View;

/**
 * Shares the "current" edition with the public header — bound only to
 * layouts.partials.public-header, so this one extra query never runs
 * for admin/guest/maintenance pages. Used solely to link the header's
 * "Points Table" item at the current edition's own page (which already
 * renders full standings) without inventing a new route.
 */
class PublicNavComposer
{
    public function compose(View $view): void
    {
        $view->with('currentEditionForNav', Edition::current());
    }
}
