<?php

declare(strict_types=1);

namespace Kameleoon\Types;

use Kameleoon\Configuration\Rule as InternalRule;
use Kameleoon\Helpers\StringHelper;

class Rule
{
    /**
     * @var array<string, Variation>
     */
    public array $variations;

    /**
     * @internal
     * @param array<string, Variation> $variations
     */
    public function __construct(array $variations)
    {
        $this->variations = $variations;
    }

    /**
     * @param array<string, Variation> $variations
     */
    public static function buildFromInternal(InternalRule $internalRule, array $variations): self
    {
        $ruleVariations = [];
        foreach ($internalRule->experiment->variationsByExposition as $varByExp) {
            $baseVariation = $variations[$varByExp->variationKey] ?? null;
            if ($baseVariation === null) {
                continue;
            }
            $ruleVariations[$baseVariation->key] = new Variation(
                $baseVariation->key,
                $varByExp->variationId,
                $internalRule->experiment->id,
                $baseVariation->variables,
                $baseVariation->name
            );
        }
        return new self($ruleVariations);
    }

    public function __toString(): string
    {
        $variations = StringHelper::sarray($this->variations);
        return "Rule{variations:$variations}";
    }
}
