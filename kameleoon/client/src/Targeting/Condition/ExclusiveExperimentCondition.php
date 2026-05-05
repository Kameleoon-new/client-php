<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Logging\KameleoonLogger;

class ExclusiveExperimentCondition extends VisitorScopeCondition
{
    private const CAMPAIGN_TYPE_EXPERIMENT = "EXPERIMENT";
    private const CAMPAIGN_TYPE_PERSONALIZATION = "PERSONALIZATION";
    private const CAMPAIGN_TYPE_ANY = "ANY";

    const TYPE = "EXCLUSIVE_EXPERIMENT";

    private string $campaignType;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData, VisitorScopeCondition::VISIT_SCOPE_VISITOR);
        $this->campaignType = $conditionData->campaignType ?? null;
    }

    public function check($data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $assignmentThreshold = $this->getAssignmentThresholdMillis($data["visitorVisits"] ?? null);
        $currentExperimentId = $data["currentExperimentId"] ?? -1;
        $variations = $data["variations"] ?? [];
        $personalizations = $data["personalizations"] ?? [];
        switch ($this->campaignType) {
            case self::CAMPAIGN_TYPE_EXPERIMENT:
                return self::checkExperiment($currentExperimentId, $variations, $assignmentThreshold);
            case self::CAMPAIGN_TYPE_PERSONALIZATION:
                return self::checkPersonalization($personalizations, $assignmentThreshold);
            case self::CAMPAIGN_TYPE_ANY:
                return self::checkExperiment($currentExperimentId, $variations, $assignmentThreshold)
                    && self::checkPersonalization($personalizations, $assignmentThreshold);
            default:
                break;
        }
        KameleoonLogger::error(
            "Unexpected campaign type for 'ExclusiveExperimentCondition' condition: '%s'",
            $this->campaignType
        );
        return false;
    }

    private static function checkExperiment(int $currentExperimentId, array $variations, int $assignmentThreshold): bool
    {
        foreach ($variations as $variation) {
            if (
                $variation->getExperimentId() !== $currentExperimentId
                && $variation->getAssignmentDateMillis() >= $assignmentThreshold
            ) {
                return false;
            }
        }
        return true;
    }

    private static function checkPersonalization(array $personalizations, int $assignmentThreshold): bool
    {
        foreach ($personalizations as $personalization) {
            if ($personalization->getAssignmentDateMillis() >= $assignmentThreshold) {
                return false;
            }
        }
        return true;
    }
}
