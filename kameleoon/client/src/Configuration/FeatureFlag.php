<?php

namespace Kameleoon\Configuration;

class FeatureFlag
{
    public int $id;
    public string $featureKey;
    private bool $environmentEnabled;
    public string $defaultVariationKey;
    public ?string $meGroupName;
    public ?int $bucketingCustomDataIndex;
    private array $variations;
    public array $rules;

    public function __construct($ff, array $segments, CustomDataInfo $cdi)
    {
        $this->id = $ff->id ?? 0;
        $this->featureKey = $ff->featureKey ?? '';
        $this->environmentEnabled = $ff->environmentEnabled ?? false;
        $this->defaultVariationKey = $ff->defaultVariationKey ?? '';
        $this->meGroupName = $ff->mutuallyExclusiveGroup ?? null;
        $bucketingCustomDataId = $ff->bucketingCustomDataId ?? null;
        $this->bucketingCustomDataIndex = ($bucketingCustomDataId !== null)
            ? $cdi->getCustomDataIndexById($bucketingCustomDataId) : null;
        $this->variations = array_map(fn($var) => new Variation($var), $ff->variations);
        $this->rules = array_map(fn($rule) => new Rule($rule, $segments), $ff->rules);
    }

    public function getEnvironmentEnabled(): bool
    {
        return $this->environmentEnabled;
    }

    public function getVariation(string $key): ?Variation
    {
        $arrayVariation = array_filter($this->variations, function ($v) use ($key) {
            return $v->key == $key;
        }, ARRAY_FILTER_USE_BOTH);
        return array_pop($arrayVariation);
    }

    public function getVariations(): array
    {
        return $this->variations;
    }

    public function __toString(): string
    {
        return "FeatureFlag{" .
            "id:" . $this->id .
            ",featureKey:'" . $this->featureKey .
            "',environmentEnabled:" . ($this->environmentEnabled ? 'true' : 'false') .
            ",defaultVariationKey:'" . $this->defaultVariationKey .
            "',meGroupName:'" . $this->meGroupName .
            "',rules:" . count($this->rules) .
            "',bucketingCustomDataIndex:" . $this->bucketingCustomDataIndex .
            "}";
    }
}
