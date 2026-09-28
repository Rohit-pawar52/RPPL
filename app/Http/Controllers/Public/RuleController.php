<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RuleType;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public Rules & Regulations page. Read-only, no authorization.
 *
 * Only active RuleTypes that still have at least one active Rule are
 * exposed as tabs — a type that is active but whose rules are all
 * inactive would otherwise render an empty tab. The selected tab comes
 * from ?type=<slug>; a missing, unknown, or inactive slug silently
 * falls back to the first visible type (sort_order), never a 404.
 */
class RuleController extends Controller
{
    public function index(Request $request): View
    {
        // Eager-loading the active rules for every active type is a
        // single extra query for what is realistically a handful of
        // rows, and lets the visibility filter and the selected type's
        // list share the same data.
        $activeTypes = RuleType::query()
            ->active()
            ->ordered()
            ->with(['rules' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->filter(fn (RuleType $type) => $type->rules->isNotEmpty())
            ->values();

        if ($activeTypes->isEmpty()) {
            return view('public.rules.index', [
                'activeTypes' => $activeTypes,
                'selectedType' => null,
                'rules' => collect(),
            ]);
        }

        $requestedSlug = $request->query('type');

        $selectedType = (is_string($requestedSlug)
            ? $activeTypes->firstWhere('slug', $requestedSlug)
            : null) ?? $activeTypes->first();

        return view('public.rules.index', [
            'activeTypes' => $activeTypes,
            'selectedType' => $selectedType,
            'rules' => $selectedType->rules,
        ]);
    }
}
