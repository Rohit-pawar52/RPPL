<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rule\StoreRuleRequest;
use App\Http\Requests\Admin\Rule\UpdateRuleRequest;
use App\Models\Rule;
use App\Models\RuleType;
use App\Services\Rule\RuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for individual Rules & Regulations entries. All image
 * store/replace/remove/cleanup goes through RuleService — this
 * controller never touches Storage directly. Activate/deactivate is
 * simply the `status` field on the edit form (no separate toggle route),
 * same as VideoController.
 */
class RuleController extends Controller
{
    public function __construct(private readonly RuleService $rules) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rule::class);

        $filters = $request->only(['search', 'rule_type_id', 'status']);

        // Deliberately NOT scoped to active(): the admin list shows every
        // rule regardless of its own or its type's status unless filtered.
        $rules = Rule::query()
            ->with('ruleType')
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%')
            )
            ->when(
                $filters['rule_type_id'] ?? null,
                fn ($query, $ruleTypeId) => $query->where('rule_type_id', $ruleTypeId)
            )
            ->when(
                in_array($filters['status'] ?? null, Rule::STATUSES, true) ? $filters['status'] : null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.rules.index', [
            'rules' => $rules,
            'filters' => $filters,
            'ruleTypes' => RuleType::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Rule::class);

        return view('admin.rules.create', [
            'ruleTypes' => RuleType::query()->ordered()->get(),
        ]);
    }

    public function store(StoreRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', Rule::class);

        $this->rules->createRule(
            $this->withImportantFlag($request, $request->safe()->except('image')),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.rules.index')
            ->with('success', __('Rule created successfully.'));
    }

    public function edit(Rule $rule): View
    {
        $this->authorize('update', $rule);

        return view('admin.rules.edit', [
            'rule' => $rule,
            'ruleTypes' => RuleType::query()->ordered()->get(),
        ]);
    }

    /**
     * The image is optional here — when not re-uploaded (and
     * remove_image isn't ticked), RuleService keeps the existing one.
     */
    public function update(UpdateRuleRequest $request, Rule $rule): RedirectResponse
    {
        $this->authorize('update', $rule);

        $this->rules->updateRule(
            $rule,
            $this->withImportantFlag($request, $request->safe()->except(['image', 'remove_image'])),
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()
            ->route('admin.rules.index')
            ->with('success', __('Rule updated successfully.'));
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $this->authorize('delete', $rule);

        $this->rules->deleteRule($rule);

        return redirect()
            ->route('admin.rules.index')
            ->with('success', __('Rule deleted successfully.'));
    }

    /**
     * is_important is a checkbox: unticked means the field is absent from
     * the request entirely (and so absent from validated data), which
     * would otherwise leave an existing `true` unchanged on update.
     * Always set it explicitly from the request instead.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withImportantFlag(Request $request, array $data): array
    {
        return [
            ...$data,
            'is_important' => $request->boolean('is_important'),
        ];
    }
}
