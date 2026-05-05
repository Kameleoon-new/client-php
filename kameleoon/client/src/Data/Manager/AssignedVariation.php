<?php

declare(strict_types=1);

namespace Kameleoon\Data\Manager;

use Kameleoon\Configuration\Rule;
use Kameleoon\Data\BaseData;
use Kameleoon\Helpers\TimeHelper;
use Kameleoon\Network\QueryBuilder;
use Kameleoon\Network\QueryParam;
use Kameleoon\Network\QueryParams;
use Kameleoon\Network\Sendable;

/** @internal */
class AssignedVariation extends Sendable implements BaseData
{
    const RULE_TYPE_UNKNOWN = -1;
    const RULE_TYPE_EXPERIMENTATION = 0;
    const RULE_TYPE_TARGETED_DELIVERY = 1;

    public const EVENT_TYPE = "experiment";

    private int $experimentId;
    private int $variationId;
    private int $assignmentDateMillis;
    private int $ruleType;

    public function __construct(
        int $experimentId,
        int $variationId,
        int $ruleType = AssignedVariation::RULE_TYPE_UNKNOWN,
        ?int $assignmentDateMillis = null
    ) {
        $this->experimentId = $experimentId;
        $this->variationId = $variationId;
        $this->assignmentDateMillis = $assignmentDateMillis ?? TimeHelper::nowInMilliseconds();
        $this->ruleType = $ruleType;
    }

    public function getExperimentId(): int
    {
        return $this->experimentId;
    }

    public function getVariationId(): int
    {
        return $this->variationId;
    }

    public function getRuleType(): int
    {
        return $this->ruleType;
    }

    public function isValid(?int $respoolTime): bool
    {
        return ($respoolTime == null) || ($this->assignmentDateMillis >= $respoolTime);
    }

    public function getAssignmentDateMillis(): int
    {
        return $this->assignmentDateMillis;
    }

    public function getQuery(): string
    {
        return (string)new QueryBuilder(
            new QueryParam(QueryParams::EVENT_TYPE, self::EVENT_TYPE),
            new QueryParam(QueryParams::EXPERIMENT_ID, (string)$this->experimentId),
            new QueryParam(QueryParams::VARIATION_ID, (string)$this->variationId),
            new QueryParam(QueryParams::NONCE, $this->getNonce()),
        );
    }

    public function __toString(): string
    {
        return "AssignedVariation{" .
            "experimentId:" . $this->experimentId .
            ",variationId:" . $this->variationId .
            ",assignmentDateMillis:" . $this->assignmentDateMillis .
            ",ruleType:" . $this->ruleType .
            "}";
    }

    public static function convertLiteralRuleTypeToEnum(string $literal): int
    {
        switch ($literal) {
            case Rule::EXPERIMENTATION:
                return AssignedVariation::RULE_TYPE_EXPERIMENTATION;
            case Rule::TARGETED_DELIVERY:
                return AssignedVariation::RULE_TYPE_TARGETED_DELIVERY;
            default:
                return AssignedVariation::RULE_TYPE_UNKNOWN;
        }
    }
}
