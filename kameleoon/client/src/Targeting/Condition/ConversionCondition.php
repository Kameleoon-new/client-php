<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

class ConversionCondition extends VisitorScopeCondition
{
    const TYPE = "CONVERSIONS";

    private int $goalId;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData, VisitorScopeCondition::VISIT_SCOPE_VISITOR);
        $this->goalId = $conditionData->goalId ?? TargetingCondition::NON_EXISTENT_IDENTIFIER;
    }

    public function check($data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $visitorVisits = $data["visitorVisits"] ?? null;
        $conversions = $data["conversions"] ?? [];
        if (!is_iterable($conversions)) {
            return false;
        }
        $assignmentThreshold = $this->getAssignmentThresholdMillis($visitorVisits);
        foreach ($conversions as $conversion) {
            if (
                ($this->goalId === TargetingCondition::NON_EXISTENT_IDENTIFIER
                    || $this->goalId === $conversion->getGoalId())
                && $conversion->getAssignmentDateMillis() >= $assignmentThreshold
            ) {
                return true;
            }
        }
        return false;
    }
}
