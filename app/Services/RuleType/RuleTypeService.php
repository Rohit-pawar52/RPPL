<?php

namespace App\Services\RuleType;

use App\Models\RuleType;

/**
 * Plain CRUD except for delete, which is guarded exactly like
 * TeamService::deleteTeam(): returns false instead of letting the
 * rules.rule_type_id RESTRICT foreign key fail, so the controller can
 * show a friendly message rather than a raw DB error. Deactivating a
 * type (is_active = false) is the normal way to retire it without
 * losing its Rules — see RuleType's own docblock.
 */
class RuleTypeService
{
    public function createType(array $data): RuleType
    {
        return RuleType::create($data);
    }

    public function updateType(RuleType $ruleType, array $data): RuleType
    {
        $ruleType->update($data);

        return $ruleType;
    }

    public function deleteType(RuleType $ruleType): bool
    {
        if ($this->hasRules($ruleType)) {
            return false;
        }

        return (bool) $ruleType->delete();
    }

    public function hasRules(RuleType $ruleType): bool
    {
        return $ruleType->rules()->exists();
    }
}
