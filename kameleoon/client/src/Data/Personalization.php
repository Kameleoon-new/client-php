<?php

namespace Kameleoon\Data;

use Kameleoon\Helpers\TimeHelper;

/** @internal */
class Personalization implements BaseData
{
    private int $id;
    private int $variationId;
    private int $assignmentDateMillis;

    public function __construct(int $id, int $variationId, ?int $assignmentDateMillis = null)
    {
        $this->id = $id;
        $this->variationId = $variationId;
        $this->assignmentDateMillis = $assignmentDateMillis ?? TimeHelper::nowInMilliseconds();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getVariationId(): int
    {
        return $this->variationId;
    }

    public function getAssignmentDateMillis(): int
    {
        return $this->assignmentDateMillis;
    }

    public function __toString(): string
    {
        return "Personalization{id:" . $this->id .
            ",variationId:" . $this->variationId .
            ",assignmentDateMillis:" . $this->assignmentDateMillis .
            "}";
    }
}
