<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Configuration\DataFile;

class TargetFeatureFlagCondition extends VisitorScopeCondition
{
    const TYPE = "TARGET_FEATURE_FLAG";

    private int $featureFlagId;
    private ?string $conditionVariationKey;
    private int $conditionRuleId;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData, VisitorScopeCondition::VISIT_SCOPE_CURRENT_VISIT);
        $this->featureFlagId = intval($conditionData->featureFlagId ?? "-1");
        $this->conditionVariationKey = $conditionData->variationKey ?? null;
        $this->conditionRuleId = intval($conditionData->ruleId ?? null);
    }

    public function check($data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $dataFile = $data["dataFile"] ?? null;
        if ($dataFile === null) {
            return false;
        }
        $variations = $data["variations"] ?? [];
        $assignmentThreshold = $this->getAssignmentThresholdMillis($data["visitorVisits"] ?? null);
        foreach ($this->getRules($dataFile) as $rule) {
            if ($rule === null || $rule->experiment->id === null) {
                continue;
            }
            $assignedVariation = $variations[$rule->experiment->id] ?? null;
            if ($assignedVariation === null || $assignedVariation->getAssignmentDateMillis() < $assignmentThreshold) {
                continue;
            }
            if ($this->conditionVariationKey === null) {
                return true;
            }
            $variation = $dataFile->getVariation($assignedVariation->getVariationId());
            if ($variation !== null && $variation->variationKey === $this->conditionVariationKey) {
                return true;
            }
        }
        return false;
    }

    private function getRules(DataFile $dataFile): array
    {
        $ff = $dataFile->getFeatureFlagById($this->featureFlagId);
        if ($ff !== null) {
            if ($this->conditionRuleId > 0) {
                foreach ($ff->rules as $rule) {
                    if ($rule->id === $this->conditionRuleId) {
                        return [$rule];
                    }
                }
            } else {
                return $ff->rules;
            }
        }
        return [];
    }
}
