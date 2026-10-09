<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RuleType\StoreRuleTypeRequest;
use App\Http\Requests\Admin\RuleType\UpdateRuleTypeRequest;
use App\Models\RuleType;
use App\Services\RuleType\RuleTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for Rule Types (the categories Rules are grouped under,
 * e.g. "Cricket Rules"). Reached from a link on the Rules index rather
 * than its own sidebar entry. Delete is guarded by
 * RuleTypeService::deleteType(): a type that still has Rules is never
 * deleted — the admin gets a friendly error instead of the RESTRICT
 * foreign key's raw DB exception, same pattern as TeamController.
 */
class RuleTypeController extends Controller
{
    public function __construct(private readonly RuleTypeService $ruleTypes) {}

    public function index(): View
    {
        $this->authorize('viewAny', RuleType::class);

        $ruleTypes = RuleType::query()
            ->withCount('rules')
            ->ordered()
            ->paginate(20);

        return view('admin.rule-types.index', [
            'ruleTypes' => $ruleTypes,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RuleType::class);

        return view('admin.rule-types.create');
    }

    public function store(StoreRuleTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', RuleType::class);

        $this->ruleTypes->createType($request->validated());

        return redirect()
            ->route('admin.rule-types.index')
            ->with('success', __('Rule type created successfully.'));
    }

    public function edit(RuleType $ruleType): View
    {
        $this->authorize('update', $ruleType);

        return view('admin.rule-types.edit', [
            'ruleType' => $ruleType,
        ]);
    }

    public function update(UpdateRuleTypeRequest $request, RuleType $ruleType): RedirectResponse
    {
        $this->authorize('update', $ruleType);

        $this->ruleTypes->updateType($ruleType, $request->validated());

        return redirect()
            ->route('admin.rule-types.index')
            ->with('success', __('Rule type updated successfully.'));
    }

    public function destroy(RuleType $ruleType): RedirectResponse
    {
        $this->authorize('delete', $ruleType);

        if (! $this->ruleTypes->deleteType($ruleType)) {
            return redirect()
                ->route('admin.rule-types.index')
                ->with('error', __('This rule type cannot be deleted because it still has rules. Move or delete its rules first, or deactivate the rule type instead if it should no longer be shown.'));
        }

        return redirect()
            ->route('admin.rule-types.index')
            ->with('success', __('Rule type deleted successfully.'));
    }
}
