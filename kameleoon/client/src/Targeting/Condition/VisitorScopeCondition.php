<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Data\VisitorVisits;

abstract class VisitorScopeCondition extends TargetingCondition
{
    public const VISIT_SCOPE_CURRENT_VISIT = "CURRENT_VISIT";
    public const VISIT_SCOPE_VISITOR = "VISITOR";

    private const MIN_VISITOR_COUNT = 2;
    private const MAX_VISITOR_COUNT = 25;

    private string $visitScope;
    private int $visitCount;

    public function __construct($conditionData, string $defaultVisitScope)
    {
        parent::__construct($conditionData);
        $this->visitScope = self::parseVisitScope($conditionData->visitScope ?? null, $defaultVisitScope);
        $visitCount = intval($conditionData->visitCount ?? self::MAX_VISITOR_COUNT);
        $this->visitCount = $visitCount > 0 ? $visitCount : self::MAX_VISITOR_COUNT;
    }

    protected function getAssignmentThresholdMillis(?VisitorVisits $visitorVisits): int
    {
        if ($visitorVisits === null) {
            return 0;
        }
        $prevVisits = $visitorVisits->getPrevVisits();
        if (
            $this->visitScope === self::VISIT_SCOPE_CURRENT_VISIT
            || $this->visitCount < self::MIN_VISITOR_COUNT
            || count($prevVisits) === 0
        ) {
            return $visitorVisits->getTimeStarted();
        }

        $visitIndex = max(0, min($this->visitCount - self::MIN_VISITOR_COUNT, count($prevVisits) - 1));
        return $prevVisits[$visitIndex]->getTimeStarted();
    }

    private static function parseVisitScope($value, string $defaultVisitScope): string
    {
        if (!is_string($value)) {
            return $defaultVisitScope;
        }
        switch (strtoupper($value)) {
            case self::VISIT_SCOPE_CURRENT_VISIT:
                return self::VISIT_SCOPE_CURRENT_VISIT;
            case self::VISIT_SCOPE_VISITOR:
                return self::VISIT_SCOPE_VISITOR;
            default:
                return $defaultVisitScope;
        }
    }
}
