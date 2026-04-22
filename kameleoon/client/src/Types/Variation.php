<?php

declare(strict_types=1);

namespace Kameleoon\Types;

use Kameleoon\Configuration;
use Kameleoon\Configuration\Variation as InternalVariation;
use Kameleoon\Helpers\StringHelper;

class Variation
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $key;

    /**
     * @var ?int
     */
    public ?int $id;

    /**
     * @var ?int
     */
    public ?int $experimentId;

    /**
     * @var array<string, Variable>
     */
    public array $variables;

    /**
     * @param string $key
     * @param ?int $id
     * @param ?int $experimentId
     * @param array<string, Variable> $variables
     * @param string $name
     */
    public function __construct(
        string $key,
        ?int $id,
        ?int $experimentId,
        array $variables,
        string $name = ''
    ) {
        $this->key = $key;
        $this->id = $id;
        $this->experimentId = $experimentId;
        $this->variables = $variables;
        $this->name = $name;
    }

    /**
     * @return bool `true` if the variation is active, otherwise `false`.
     */
    public function isActive(): bool
    {
        return $this->key != Configuration\Variation::VARIATION_OFF;
    }

    public static function buildFromInternal(
        ?InternalVariation $sourceVariation,
        ?int $variationId = null,
        ?int $experimentId = null
    ): self {
        $variables = [];
        $sourceVariables = ($sourceVariation !== null) ? $sourceVariation->variables : [];
        foreach ($sourceVariables as $variable) {
            $variables[$variable->key] = new Variable($variable->key, $variable->type, $variable->getValue());
        }

        $variationKey = ($sourceVariation !== null) ? $sourceVariation->key : '';

        return new self(
            $variationKey,
            $variationId,
            $experimentId,
            $variables,
            ($sourceVariation !== null) ? $sourceVariation->name : ''
        );
    }

    public function __toString(): string
    {
        $variables = StringHelper::objectToString($this->variables);
        return "Variation{name:'$this->name',key:'$this->key',id:$this->id,experimentId:$this->experimentId,variables:$variables}";
    }
}
