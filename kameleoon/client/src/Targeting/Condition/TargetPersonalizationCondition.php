<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

class TargetPersonalizationCondition extends VisitorScopeCondition
{
    const TYPE = "TARGET_PERSONALIZATION";

    private int $personalizationId;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData, VisitorScopeCondition::VISIT_SCOPE_CURRENT_VISIT);
        $this->personalizationId = $conditionData->personalizationId ?? -1;
    }

    public function check($data): bool
    {
        if (!is_array($data) || $this->personalizationId === TargetingCondition::NON_EXISTENT_IDENTIFIER) {
            return false;
        }
        $visitorVisits = $data["visitorVisits"] ?? null;
        $personalizations = $data["personalizations"] ?? [];
        $personalization = $personalizations[$this->personalizationId] ?? null;
        return $personalization !== null
            && $personalization->getAssignmentDateMillis() >= $this->getAssignmentThresholdMillis($visitorVisits);
    }
}
