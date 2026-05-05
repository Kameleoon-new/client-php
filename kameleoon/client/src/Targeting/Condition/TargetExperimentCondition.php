<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Logging\KameleoonLogger;

class TargetExperimentCondition extends VisitorScopeCondition
{
    const TYPE = "TARGET_EXPERIMENT";

    private int $variationId;
    private int $experimentId;
    private string $variationMatchType;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData, VisitorScopeCondition::VISIT_SCOPE_CURRENT_VISIT);
        $this->variationId = $conditionData->variationId ?? -1;
        $this->experimentId = $conditionData->experimentId ?? -1;
        $this->variationMatchType = $conditionData->variationMatchType ?? TargetingOperator::UNKNOWN;
    }

    public function check($data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $visitorVisits = $data["visitorVisits"] ?? null;
        $variations = $data["variations"] ?? [];
        $variation = $variations[$this->experimentId] ?? null;
        $variationExists = $variation !== null
            && $variation->getAssignmentDateMillis() >= $this->getAssignmentThresholdMillis($visitorVisits);
        switch ($this->variationMatchType) {
            case TargetingOperator::ANY:
                return $variationExists;
            case TargetingOperator::EXACT:
                return $variationExists && $variation->getVariationId() == $this->variationId;
            default:
                break;
        }
        KameleoonLogger::error(
            "Unexpected variation match type for 'TargetExperimentCondition' condition: '%s'",
            $this->variationMatchType
        );
        return false;
    }
}
