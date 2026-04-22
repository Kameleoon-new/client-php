<?php

declare(strict_types=1);

namespace Kameleoon\Configuration;

use Exception;
use Kameleoon\Exception\FeatureEnvironmentDisabled;
use Kameleoon\Exception\FeatureNotFound;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Targeting\TargetingSegment;
use Throwable;

class DataFile
{
    private ?string $lastModified;
    private int $dateModified;
    private array $featureFlags;
    private array $meGroups;
    private Settings $settings;
    private ?string $environment;
    private bool $hasAnyTDRule;
    private array $segments;
    private array $audienceTrackingSegments;
    private array $featureFlagById;
    private array $ruleInfoByExpId;
    private array $variationById;
    private CustomDataInfo $customDataInfo;
    private array $experimentIdsWithJsCssVariable;
    private ?Experiment $holdout;

    public function __construct(object $jsonDataFile, ?string $environment = null)
    {
        KameleoonLogger::debug(
            "CALL: new DataFile(jsonDataFile: %s, environment: '%s')",
            $jsonDataFile,
            $environment
        );
        $this->environment = $environment;
        $this->lastModified = self::readLastModified($jsonDataFile);
        $this->dateModified = self::readDateModified($jsonDataFile);
        [$this->segments, $this->audienceTrackingSegments] = self::parseSegments($jsonDataFile);
        $this->customDataInfo = new CustomDataInfo($jsonDataFile->customData ?? null);
        $this->featureFlags = self::parseFeatureFlags($jsonDataFile, $this->segments, $this->customDataInfo);
        $this->meGroups = self::makeMEGroups($this->featureFlags);
        $this->settings = new Settings($jsonDataFile);
        $this->holdout = is_object($jsonDataFile->holdout ?? null) ? new Experiment($jsonDataFile->holdout) : null;
        KameleoonLogger::debug(
            "RETURN: new DataFile(jsonDataFile: %s, environment: '%s')",
            $jsonDataFile,
            $environment
        );
    }

    public function getLastModified(): ?string
    {
        return $this->lastModified;
    }

    public function getDateModified(): int
    {
        return $this->dateModified;
    }

    public function &getSegments(): array
    {
        return $this->segments;
    }

    public function &getAudienceTrackingSegments(): array
    {
        return $this->audienceTrackingSegments;
    }

    public function getFeatureFlags(): array
    {
        return $this->featureFlags;
    }

    public function &getMEGroups(): array
    {
        return $this->meGroups;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function getCustomDataInfo(): CustomDataInfo
    {
        return $this->customDataInfo;
    }

    public function getHoldout(): ?Experiment
    {
        return $this->holdout;
    }

    public function hasAnyTargetedDeliveryRule(): bool
    {
        if (!isset($this->hasAnyTDRule)) {
            $this->hasAnyTDRule = $this->detIfHasAnyTargetedDeliveryRule();
        }
        return $this->hasAnyTDRule;
    }

    public function getFeatureFlagById(int $featureFlagId): ?FeatureFlag
    {
        KameleoonLogger::debug("CALL: DataFile->getFeatureFlagById(featureFlagId: %s)", $featureFlagId);
        if (!isset($this->featureFlagById)) {
            $this->featureFlagById = $this->collectFeatureFlagById();
        }
        $featureFlag = $this->featureFlagById[$featureFlagId] ?? null;
        KameleoonLogger::debug(
            "RETURN: DataFile->getFeatureFlagById(featureFlagId: %s) -> (featureFlag: %s)",
            $featureFlagId,
            $featureFlag
        );
        return $featureFlag;
    }

    public function getRuleInfoByExpId(int $experimentId): ?RuleInfo
    {
        KameleoonLogger::debug("CALL: DataFile->getRuleInfoByExpId(experimentId: %s)", $experimentId);
        if (!isset($this->ruleInfoByExpId)) {
            $this->ruleInfoByExpId = $this->collectRuleInfoByExpId();
        }
        $ruleInfo = $this->ruleInfoByExpId[$experimentId] ?? null;
        KameleoonLogger::debug(
            "RETURN: DataFile->getRuleInfoByExpId(experimentId: %s) -> (ruleInfo: %s)",
            $experimentId,
            $ruleInfo
        );
        return $ruleInfo;
    }

    public function getVariation(int $variationId): ?VariationByExposition
    {
        KameleoonLogger::debug("CALL: DataFile->getVariation(variationId: %s)", $variationId);
        if (!isset($this->variationById)) {
            $this->variationById = $this->collectVariationById();
        }
        $variation = $this->variationById[$variationId] ?? null;
        KameleoonLogger::debug(
            "RETURN: DataFile->getVariation(variationId: %s) -> (featureFlag: %s)",
            $variationId,
            $variation
        );
        return $variation;
    }

    public function hasExperimentJsCssVariable(int $experimentId): bool
    {
        if (!isset($this->experimentIdsWithJsCssVariable)) {
            $this->experimentIdsWithJsCssVariable = $this->collectExperimentIdsWithJsCssVariable();
        }
        return array_key_exists($experimentId, $this->experimentIdsWithJsCssVariable);
    }

    private static function readLastModified($json): ?string
    {
        $lastModified = $json->phpsdk_datafile_lastmod ?? null;
        return is_string($lastModified) ? $lastModified : null;
    }

    private static function readDateModified($json): int
    {
        $dateModified = $json->dateModified ?? null;
        return is_numeric($dateModified) ? (int) $dateModified : 0;
    }

    private static function parseSegments($json): array
    {
        $segments = [];
        $audienceTrackingSegments = [];
        try {
            foreach ($json->segments as $obj) {
                $segmentId = $obj->id ?? null;
                if ($segmentId !== null) {
                    $segment = new TargetingSegment($obj);
                    $segments[$segmentId] = $segment;
                    if ($obj->audienceTracking ?? false) {
                        $audienceTrackingSegments[] = $segment;
                    }
                }
            }
        } catch (Exception $e) {
            KameleoonLogger::error("Failed to parse segments in configuration: " . $e->getMessage());
            return [[], []];
        }
        return [$segments, $audienceTrackingSegments];
    }

    private static function parseFeatureFlags($json, array $segments, CustomDataInfo $cdi): array
    {
        $ffs = [];
        try {
            foreach ($json->featureFlags as $obj) {
                $ffs[$obj->featureKey] = new FeatureFlag($obj, $segments, $cdi);
            }
        } catch (Throwable $e) {
            KameleoonLogger::error("Failed to parse feature flags in configuration: " . $e->getMessage());
            return [];
        }
        return $ffs;
    }

    public function getFeatureFlag(string $featureKey): FeatureFlag
    {
        KameleoonLogger::debug("CALL: DataFile->getFeatureFlag(featureKey: '%s')", $featureKey);
        $featureFlag = $this->featureFlags[$featureKey] ?? null;
        if ($featureFlag === null) {
            throw new FeatureNotFound("Feature {$featureKey} not found");
        }
        KameleoonLogger::debug("RETURN: DataFile->getFeatureFlag(featureKey: '%s') -> (featureFlag: %s)",
            $featureKey, $featureFlag);
        return $featureFlag;
    }

    public function ensureEnvironmentEnabled(FeatureFlag $featureFlag): void
    {
        if (!$featureFlag->getEnvironmentEnabled()) {
            $environment = $this->environment ?? 'default';
            throw new FeatureEnvironmentDisabled("Feature '{$featureFlag->featureKey}' is disabled for {$environment} environment");
        }
    }

    private static function makeMEGroups(array $featureFlags): array
    {
        $meGroupLists = [];
        foreach ($featureFlags as $featureFlag) {
            if ($featureFlag->meGroupName !== null) {
                if (array_key_exists($featureFlag->meGroupName, $meGroupLists)) {
                    $meGroupLists[$featureFlag->meGroupName][] = $featureFlag;
                } else {
                    $meGroupLists[$featureFlag->meGroupName] = [$featureFlag];
                }
            }
        }
        $meGroups = [];
        foreach ($meGroupLists as $meGroupName => $meGroupList) {
            $meGroups[$meGroupName] = new MEGroup($meGroupList);
        }
        return $meGroups;
    }

    private function detIfHasAnyTargetedDeliveryRule(): bool
    {
        foreach ($this->featureFlags as $featureFlag) {
            if ($featureFlag->getEnvironmentEnabled()) {
                foreach ($featureFlag->rules as $rule) {
                    if ($rule->isTargetedDelivery()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function collectFeatureFlagById(): array
    {
        $featureFlagById = [];
        foreach ($this->featureFlags as $ffKey => $ff) {
            $featureFlagById[$ff->id] = $ff;
        }
        return $featureFlagById;
    }

    private function collectRuleInfoByExpId(): array
    {
        $ruleInfoByExpId = [];
        foreach ($this->featureFlags as $ffKey => $ff) {
            foreach ($ff->rules as $ruleKey => $rule) {
                $ruleInfoByExpId[$rule->experiment->id] = new RuleInfo($ff, $rule);
            }
        }
        return $ruleInfoByExpId;
    }

    private function collectVariationById(): array
    {
        $variationById = [];
        foreach ($this->featureFlags as $ffKey => $ff) {
            foreach ($ff->rules as $ruleKey => $rule) {
                foreach ($rule->experiment->variationsByExposition as $varKey => $variation) {
                    $variationById[$variation->variationId] = $variation;
                }
            }
        }
        return $variationById;
    }

    private function collectExperimentIdsWithJsCssVariable(): array
    {
        $experimentIdsWithJSOrCSS = [];
        foreach ($this->featureFlags as $featureFlag) {
            $hasFeatureFlagVariableJsCss = $this->hasFeatureFlagVariableJsCss($featureFlag);
            foreach ($featureFlag->rules as $rule) {
                if ($hasFeatureFlagVariableJsCss) {
                    $experimentIdsWithJSOrCSS[$rule->experiment->id] = true;
                }
            }
        }
        return $experimentIdsWithJSOrCSS;
    }

    private function hasFeatureFlagVariableJsCss(FeatureFlag $featureFlag): bool
    {
        $variations = $featureFlag->getVariations();
        if (!empty($variations)) {
            $firstVariation = $variations[0];
            foreach ($firstVariation->variables as $variable) {
                if ($variable->type === "JS" || $variable->type === "CSS") {
                    return true;
                }
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return sprintf(
            "DataFile{timestamp:%d,environment:'%s',featureFlags:%d,settings:%s}",
            $this->timestamp ?? 0,
            $this->environment,
            count($this->featureFlags),
            (string) $this->settings,
        );
    }
}
