<?php

namespace Kameleoon\Data;

use Kameleoon\Helpers\StringHelper;
use Kameleoon\Helpers\TimeHelper;
use Kameleoon\Helpers\URLEncoding;
use Kameleoon\Network\QueryBuilder;
use Kameleoon\Network\QueryParam;
use Kameleoon\Network\QueryParams;
use Kameleoon\Network\Sendable;

class Conversion extends Sendable implements Data
{
    public const EVENT_TYPE = "conversion";

    private int $goalId;
    private float $revenue;
    private bool $negative;
    private ?array $metadata;
    private int $assignmentDateMillis;

    /**
     * @param int $goalId ID of the goal. This field is mandatory.
     * @param float $revenue Revenue of the conversion. This field is optional (`0.0` by default).
     * @param bool $negative Defines if the revenue is positive or negative.
     * This field is optional (`false` by default).
     * @param ?array<CustomData> $metadata Metadata of the conversion. This field is optional (`null` by default).
     */
    public function __construct(
        int $goalId,
        float $revenue = 0,
        bool $negative = false,
        ?array $metadata = null
    ) {
        $this->goalId = $goalId;
        $this->revenue = $revenue;
        $this->negative = $negative;
        $this->metadata = $metadata;
        $this->assignmentDateMillis = TimeHelper::nowInMilliseconds();
    }

    /** @internal */
    public static function createWithAssignmentDateMillis(
        int $goalId,
        float $revenue,
        bool $negative,
        ?array $metadata,
        int $assignmentDateMillis
    ): self {
        $conversion = new self($goalId, $revenue, $negative, $metadata);
        $conversion->assignmentDateMillis = $assignmentDateMillis;
        return $conversion;
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getRevenue(): float
    {
        return $this->revenue;
    }

    public function getNegative(): bool
    {
        return $this->negative;
    }

    public function &getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @internal */
    public function getAssignmentDateMillis(): int
    {
        return $this->assignmentDateMillis;
    }

    /** @internal */
    public function getQuery(): string
    {
        $qb = new QueryBuilder(
            new QueryParam(QueryParams::EVENT_TYPE, self::EVENT_TYPE),
            new QueryParam(QueryParams::GOAL_ID, (string)$this->goalId),
            new QueryParam(QueryParams::REVENUE, (string)$this->revenue),
            new QueryParam(QueryParams::NEGATIVE, $this->negative ? "true" : "false"),
            new QueryParam(QueryParams::NONCE, $this->getNonce()),
        );
        if (!empty($this->metadata)) {
            $qb->append(new QueryParam(QueryParams::METADATA, $this->encodeMetadata(), false));
        }
        return (string) $qb;
    }

    private function encodeMetadata(): string
    {
        $sb = "%7B"; // '{'
        $addedIndices = array();
        $addComma = false;
        foreach ($this->metadata as $mcd) {
            if (($mcd instanceof CustomData) && !array_key_exists($mcd->getIndex(), $addedIndices)) {
                if ($addComma) {
                    $sb .= "%2C"; // ','
                } else {
                    $addComma = true;
                }
                self::writeEncodedCustomData($mcd, $sb);
                $addedIndices[$mcd->getIndex()] = true;
            }
        }
        $sb .= "%7D"; // '}'
        return $sb;
    }

    private static function writeEncodedCustomData(CustomData $cd, string &$sb): void
    {
        $sb .= "%22"; // '"'
        $sb .= $cd->getIndex();
        $sb .= "%22%3A%5B"; // '":['
        $values = $cd->getValues();
        for ($i = 0; $i < count($values); $i++) {
            if ($i > 0) {
                $sb .= "%2C"; // ','
            }
            $sb .= "%22"; // '"'
            $escapedValue = str_replace(["\\", "\""], ["\\\\", "\\\""], $values[$i]);
            $sb .= URLEncoding::encodeURIComponent($escapedValue);
            $sb .= "%22"; // '"'
        }
        $sb .= "%5D"; // ']'
    }

    public function __toString(): string
    {
        return "Conversion{goalId:" . $this->goalId .
            ",revenue:" . $this->revenue .
            ",negative:" . ($this->negative ? 'true' : 'false') .
            ",metadata:" . StringHelper::objectToString($this->metadata) .
            ",assignmentDateMillis:" . $this->assignmentDateMillis .
            "}";
    }
}
