<?php

namespace Kameleoon;

use Exception;
use Kameleoon\Configuration\DataFile;
use Kameleoon\Configuration\FeatureFlag;
use Kameleoon\Configuration\Rule;
use Kameleoon\Configuration\Variation;
use Kameleoon\Data\CustomData;
use Kameleoon\Data\TargetedSegment;
use Kameleoon\Data\Manager\AssignedVariation;
use Kameleoon\Data\Manager\ForcedExperimentVariation;
use Kameleoon\Data\Manager\LegalConsent;
use Kameleoon\Data\Manager\Visitor;
use Kameleoon\Data\Manager\VisitorManager;
use Kameleoon\Data\Manager\VisitorManagerImpl;
use Kameleoon\Exception\DataFileInvalid;
use Kameleoon\Exception\FeatureEnvironmentDisabled;
use Kameleoon\Exception\FeatureExperimentNotFound;
use Kameleoon\Exception\FeatureVariableNotFound;
use Kameleoon\Exception\FeatureVariationNotFound;
use Kameleoon\Exception\KameleoonException;
use Kameleoon\Exception\SiteCodeIsEmpty;
use Kameleoon\Helpers\Hasher;
use Kameleoon\Helpers\VisitorCodeManager;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Managers\Data\DataManager;
use Kameleoon\Managers\Data\DataManagerImpl;
use Kameleoon\Managers\Evaluation\EvaluatedExperiment;
use Kameleoon\Managers\Hybrid\HybridManager;
use Kameleoon\Managers\Hybrid\HybridManagerImpl;
use Kameleoon\Managers\RemoteData\RemoteDataManager;
use Kameleoon\Managers\RemoteData\RemoteDataManagerImpl;
use Kameleoon\Managers\Tracking\TrackingManager;
use Kameleoon\Managers\Tracking\TrackingManagerImpl;
use Kameleoon\Managers\Warehouse\WarehouseManager;
use Kameleoon\Managers\Warehouse\WarehouseManagerImpl;
use Kameleoon\Network\AccessToken\AccessTokenSourceFactoryImpl;
use Kameleoon\Network\Cookie\CookieManager;
use Kameleoon\Network\Cookie\CookieManagerImpl;
use Kameleoon\Network\NetworkManager;
use Kameleoon\Network\NetworkManagerFactory;
use Kameleoon\Network\NetworkManagerFactoryImpl;
use Kameleoon\Targeting\TargetingManager;
use Kameleoon\Targeting\TargetingManagerImpl;
use Kameleoon\Types\RemoteVisitorDataFilter;

class KameleoonClientImpl implements KameleoonClient
{
    const FILE_CONFIGURATION_NAME = "kameleoon-configuration-";
    const VISITOR_CODE_MAX_LENGTH = 255;

    private DataManager $dataManager;
    private KameleoonClientConfig $clientConfig;
    private string $configurationFilePath;
    private string $visitorCode;

    private VisitorManager $visitorManager;
    private NetworkManager $networkManager;
    private CookieManager $cookieManager;
    private HybridManager $hybridManager;
    private WarehouseManager $warehouseManager;
    private TargetingManager $targetingManager;
    private RemoteDataManager $remoteDataManager;
    private TrackingManager $trackingManager;

    public function __construct(
        string $siteCode,
        KameleoonClientConfig $clientConfig,
        ?NetworkManagerFactory $networkManagerFactory = null
    ) {
        KameleoonLogger::debug(
            "CALL: new KameleoonClientImpl(siteCode: '%s', clientConfig: %s, networkManagerFactory)",
            $siteCode,
            $clientConfig,
        );
        if (empty($siteCode)) {
            throw new SiteCodeIsEmpty("Provided siteCode is empty");
        }

        $this->dataManager = new DataManagerImpl();
        $this->visitorManager = new VisitorManagerImpl($this->dataManager);
        $this->cookieManager = new CookieManagerImpl(
            $this->dataManager,
            $this->visitorManager,
            $clientConfig->getCookieOptions()
        );
        $this->targetingManager = new TargetingManagerImpl($this->dataManager, $this->visitorManager);

        $this->clientConfig = $clientConfig;

        $kameleoonWorkDir = $this->clientConfig->getKameleoonWorkDir();
        if (!empty($kameleoonWorkDir) && (substr($kameleoonWorkDir, -1) !== '/')) {
            $kameleoonWorkDir .= '/';
        }
        $this->configurationFilePath = $kameleoonWorkDir . self::FILE_CONFIGURATION_NAME . $siteCode . ".json";

        if (!(is_dir($kameleoonWorkDir) || mkdir($kameleoonWorkDir, 0755, true) || is_dir($kameleoonWorkDir))) {
            throw new KameleoonException(
                "Failed to create or access the Kameleoon working directory: $kameleoonWorkDir"
            );
        }

        $networkManagerFactory = $networkManagerFactory ?? new NetworkManagerFactoryImpl();
        $this->networkManager = $networkManagerFactory->create(
            $siteCode,
            $this->clientConfig->getEnvironment(),
            $this->clientConfig->getDefaultTimeoutMillisecond(),
            $kameleoonWorkDir,
            new AccessTokenSourceFactoryImpl(
                $this->clientConfig->getClientId(),
                $this->clientConfig->getClientSecret(),
                $kameleoonWorkDir
            ),
            $this->clientConfig->getNetworkDomain()
        );
        $this->trackingManager = new TrackingManagerImpl(
            $this->dataManager,
            $this->networkManager,
            $this->visitorManager,
            $this->clientConfig->getDebugMode(),
        );
        KameleoonLogger::debug(
            "RETURN: new KameleoonClientImpl(siteCode: '%s', clientConfig: %s, " .
                "networkManagerFactory)",
            $siteCode,
            $clientConfig,
        );
    }

    /*
     * API Methods (Public Methods)
     */

    public function getVisitorCode(?string $defaultVisitorCode = null, ?int $timeout = null): string
    {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getVisitorCode(defaultVisitorCode: '%s', timeout: %s)",
            $defaultVisitorCode,
            $timeout
        );
        if (!isset($this->visitorCode)) {
            if ($defaultVisitorCode !== null) {
                VisitorCodeManager::validateVisitorCode($defaultVisitorCode);
            }
            $this->loadConfiguration($timeout);
            $this->visitorCode = $this->cookieManager->getOrAdd($defaultVisitorCode);
        }
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getVisitorCode(defaultVisitorCode: '%s', timeout: %s) -> (visitorCode: '%s')",
            $defaultVisitorCode,
            $timeout,
            $this->visitorCode
        );
        return $this->visitorCode;
    }

    public function addData($visitorCode, ...$data)
    {
        $track = $this->resolveAddDataParams($data);
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->addData(visitorCode: '%s', track: %b, data: %s)",
            $visitorCode,
            $track,
            $data
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration();
        $this->visitorManager->addData($visitorCode, $track, ...$data);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->addData(visitorCode: '%s', track: %b, data: %s)",
            $visitorCode,
            $track,
            $data
        );
    }

    private function resolveAddDataParams(array &$args): bool
    {
        $track = true;
        if (isset($args[0]) && is_bool($args[0])) {
            $track = $args[0];
            unset($args[0]);
        } else {
            $track = true;
        }
        return $track;
    }

    public function flush(
        $visitorCode = null,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $instant = false
    ) {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->flush(visitorCode: '%s', timeout: %s, isUniqueIdentifier: %s, instant: %s)",
            $visitorCode,
            $timeout,
            $isUniqueIdentifier,
            $instant
        );
        $this->loadConfiguration($timeout);
        if ($visitorCode !== null) {
            VisitorCodeManager::validateVisitorCode($visitorCode);
            if ($isUniqueIdentifier !== null) {
                $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
            }
            $this->trackingManager->trackVisitor($visitorCode, $instant, $timeout);
        } else {
            $this->trackingManager->trackAll($instant, $timeout);
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->flush(visitorCode: '%s', timeout: %s, isUniqueIdentifier: %s, instant: %s)",
            $visitorCode,
            $timeout,
            $isUniqueIdentifier,
            $instant
        );
    }

    public function trackConversion(
        $visitorCode,
        int $goalID,
        float $revenue = 0.0,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $negative = false,
        ?array $metadata = null
    ) {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->trackConversion(visitorCode: '%s', goalID: %s, revenue: %s, timeout: %s, " .
                "isUniqueIdentifier: %s, negative: %s, metadata: %s)",
            $visitorCode,
            $goalID,
            $revenue,
            $timeout,
            $isUniqueIdentifier,
            $negative,
            $metadata,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration($timeout);
        if ($isUniqueIdentifier !== null) {
            $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
        }
        $this->addData($visitorCode, new Data\Conversion($goalID, $revenue, $negative, $metadata));
        $this->trackingManager->trackVisitor($visitorCode);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->trackConversion(visitorCode: '%s', goalID: %s, revenue: %s, timeout: %s, " .
                "isUniqueIdentifier: %s, negative: %s, metadata: %s)",
            $visitorCode,
            $goalID,
            $revenue,
            $timeout,
            $isUniqueIdentifier,
            $negative,
            $metadata,
        );
    }

    public function isFeatureActive(
        string $visitorCode,
        string $featureKey,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $track = true
    ): bool {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->isFeatureActive(visitorCode: '%s', featureKey: '%s', timeout: %s, " .
                "isUniqueIdentifier: %s, track: %s)",
            $visitorCode,
            $featureKey,
            $timeout,
            $isUniqueIdentifier,
            $track,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        if ($isUniqueIdentifier !== null) {
            $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
        }
        $this->loadConfiguration($timeout);
        try {
            $featureFlag = $this->dataManager->getDataFile()->getFeatureFlag($featureKey);
            [$variationKey,] = $this->getVariationInfo($visitorCode, $featureFlag, $track);
            $isFeatureActive = $variationKey != Variation::VARIATION_OFF;
        } catch (FeatureEnvironmentDisabled $ex) {
            KameleoonLogger::debug("Feature environment disabled");
            $isFeatureActive = false;
        }
        if ($track) {
            $this->trackingManager->trackVisitor($visitorCode);
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->isFeatureActive(visitorCode: '%s', featureKey: '%s', timeout: %s, " .
                "isUniqueIdentifier: %s, track: %s) -> (isFeatureActive: %s)",
            $visitorCode,
            $featureKey,
            $timeout,
            $isUniqueIdentifier,
            $track,
            $isFeatureActive,
        );
        return $isFeatureActive;
    }

    public function getVariation(
        string $visitorCode,
        string $featureKey,
        bool $track = true,
        ?int $timeout = null
    ): Types\Variation {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getVariation(visitorCode: '%s', featureKey: '%s', track: %s, timeout: %s)",
            $visitorCode,
            $featureKey,
            $track,
            $timeout,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration($timeout);
        $featureFlag = $this->dataManager->getDataFile()->getFeatureFlag($featureKey);
        [$variationKey, $evalExp] = $this->getVariationInfo($visitorCode, $featureFlag, $track);
        $variation = $featureFlag->getVariation($variationKey);
        $externalVariation = self::createExternalVariation($variation, $evalExp);
        if ($track) {
            $this->trackingManager->trackVisitor($visitorCode);
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getVariation(visitorCode: '%s', featureKey: '%s', track: %s, timeout: %s)" .
                " -> (variation: %s)",
            $visitorCode,
            $featureKey,
            $track,
            $timeout,
            $externalVariation,
        );
        return $externalVariation;
    }

    public function getVariations(
        string $visitorCode,
        bool $onlyActive = false,
        bool $track = true,
        ?int $timeout = null
    ): array {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getVariations(visitorCode: '%s', onlyActive: %s, track: %s, timeout: %s)",
            $visitorCode,
            $onlyActive,
            $track,
            $timeout,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration($timeout);
        $variations = array();
        foreach ($this->dataManager->getDataFile()->getFeatureFlags() as $featureFlag) {
            try {
                [$variationKey, $evalExp] = $this->getVariationInfo($visitorCode, $featureFlag, $track);
            } catch (FeatureEnvironmentDisabled $ex) {
                continue;
            }
            if ($onlyActive && ($variationKey == Variation::VARIATION_OFF)) {
                continue;
            }
            $variation = $featureFlag->getVariation($variationKey);
            $variations[$featureFlag->featureKey] = self::createExternalVariation($variation, $evalExp);
        }
        if ($track) {
            $this->trackingManager->trackVisitor($visitorCode);
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getVariations(visitorCode: '%s', onlyActive: %s, track: %s, timeout: %s)" .
                " -> (variations: %s)",
            $visitorCode,
            $onlyActive,
            $track,
            $timeout,
            $variations,
        );
        return $variations;
    }

    private function getVariationInfo(
        string $visitorCode,
        FeatureFlag $featureFlag,
        bool $track = true
    ): array {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->getVariationInfo(visitorCode: '%s', featureFlag: %s, track: %s)",
            $visitorCode,
            $featureFlag,
            $track,
        );
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        $evalExp = $this->evaluate($visitor, $visitorCode, $featureFlag, $track, true);
        $variationKey = $this->calculateVariationKey($evalExp, $featureFlag->defaultVariationKey);
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->getVariationInfo(visitorCode: '%s', featureFlag: %s, track: %s)" .
                " -> (variationKey: '%s', evalExp: %s)",
            $visitorCode,
            $featureFlag,
            $track,
            $variationKey,
            $evalExp,
        );
        return [$variationKey, $evalExp];
    }

    private function evaluate(
        ?Visitor $visitor,
        string $visitorCode,
        FeatureFlag $featureFlag,
        bool $track,
        bool $save
    ): ?EvaluatedExperiment {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->evaluate(visitor, visitorCode: '%s', featureFlag: %s, track: %s, save: %s)",
            $visitorCode,
            $featureFlag,
            $track,
            $save,
        );
        $forcedVariation = ($visitor !== null) ? $visitor->getForcedFeatureVariation($featureFlag->featureKey) : null;
        if ($forcedVariation === null || !$forcedVariation->isSimulated()) {
            $this->dataManager->getDataFile()->ensureEnvironmentEnabled($featureFlag);
        }
        if ($forcedVariation !== null) {
            $evalExp = EvaluatedExperiment::fromForcedVariation($forcedVariation);
        } elseif (
            $this->isVisitorNotInHoldout($visitor, $visitorCode, $track, $save, $featureFlag->bucketingCustomDataIndex)
            && $this->isFFUnrestrictedByMEGroup($visitor, $visitorCode, $featureFlag)
        ) {
            $evalExp = $this->calculateVariationKeyForFeature($visitorCode, $featureFlag);
        } else {
            $evalExp = null;
        }
        if ($save && (($forcedVariation === null) || !$forcedVariation->isSimulated())) {
            $this->saveVariation($visitorCode, $evalExp, $track);
        }
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->evaluate(visitor, visitorCode: '%s', featureFlag: %s, track: %s, save: %s)" .
                " -> (evalExp: %s)",
            $visitorCode,
            $featureFlag,
            $track,
            $save,
            $evalExp,
        );
        return $evalExp;
    }

    private function isFFUnrestrictedByMEGroup(?Visitor $visitor, string $visitorCode, FeatureFlag $featureFlag): bool
    {
        if ($featureFlag->meGroupName === null) {
            return true;
        }
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->isFFUnrestrictedByMEGroup(visitor, visitorCode: '%s', featureFlag: %s)",
            $visitorCode,
            $featureFlag,
        );
        $unrestricted = true;
        $meGroup = $this->dataManager->getDataFile()->getMEGroups()[$featureFlag->meGroupName];
        $codeForHash = self::getCodeForHash($visitor, $visitorCode, $featureFlag->bucketingCustomDataIndex);
        $meGroupHash = Hasher::obtainHashForMEGroup($codeForHash, $featureFlag->meGroupName);
        KameleoonLogger::debug(
            "Calculated ME group hash %s for code: '%s', meGroup: '%s'",
            $meGroupHash,
            $codeForHash,
            $featureFlag->meGroupName
        );
        $unrestricted = $meGroup->getFeatureFlagByHash($meGroupHash) === $featureFlag;
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->isFFUnrestrictedByMEGroup(visitor, visitorCode: '%s', featureFlag: %s)" .
                " -> (unrestricted: %s)",
            $visitorCode,
            $featureFlag,
            $unrestricted,
        );
        return $unrestricted;
    }

    private function isVisitorNotInHoldout(
        ?Visitor $visitor,
        string $visitorCode,
        bool $track,
        bool $save,
        ?int $bucketingCustomDataIndex
    ): bool {
        $holdout = $this->dataManager->getDataFile()->getHoldout();
        if ($holdout === null) {
            return true;
        }
        $inHoldoutVariationKey = "in-holdout";
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->isVisitorNotInHoldout(visitor, visitorCode: '%s', track: %s, save: %s"
                . "bucketingCustomDataIndex: %s)",
            $visitorCode,
            $track,
            $save,
            $bucketingCustomDataIndex
        );
        $arrayConsent = $this->getConsentAndBlockingBehaviour($visitor);
        $consent = $arrayConsent[0];
        $completelyBlockingBehaviour = $arrayConsent[1];
        if ($consent == LegalConsent::NOT_GIVEN && $completelyBlockingBehaviour) {
            throw new FeatureEnvironmentDisabled(
                "Evaluation for a holdout is blocked because visitor's consent was not provided."
            );
        }
        $isNotInHoldout = true;
        $codeForHash = self::getCodeForHash($visitor, $visitorCode, $bucketingCustomDataIndex);
        $variationHash = Hasher::obtain($codeForHash, $holdout->id);
        KameleoonLogger::debug("Calculated holdout hash %s for code '%s'", $variationHash, $codeForHash);
        $varByExp = $holdout->getVariationByHash($variationHash);
        if ($varByExp !== null) {
            $isNotInHoldout = $varByExp->variationKey !== $inHoldoutVariationKey;
            if ($save) {
                $evalExp = new EvaluatedExperiment($varByExp, $holdout, Rule::EXPERIMENTATION);
                $this->saveVariation($visitorCode, $evalExp, $track);
            }
        }
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->isVisitorNotInHoldout(visitor, visitorCode: '%s', track: %s, save: %s," .
                " bucketingCustomDataIndex: %s) -> (isNotInHoldout: %s)",
            $visitorCode,
            $track,
            $save,
            $bucketingCustomDataIndex,
            $isNotInHoldout
        );
        return $isNotInHoldout;
    }

    private static function createExternalVariation(
        ?Variation $variation,
        ?EvaluatedExperiment $evalExp
    ): Types\Variation {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl::createExternalVariation(variation: %s, evalExp: %s)",
            $variation,
            $evalExp,
        );
        $extVariation = Types\Variation::buildFromInternal(
            $variation,
            ($evalExp !== null) ? $evalExp->getVarByExp()->variationId : null,
            ($evalExp !== null) ? $evalExp->getExperiment()->id : null
        );
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl::createExternalVariation(variation: %s, evalExp: %s) -> (extVariation: %s)",
            $variation,
            $evalExp,
            $extVariation,
        );
        return $extVariation;
    }

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, true)`
     */
    public function getFeatureVariationKey(
        string $visitorCode,
        string $featureKey,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null
    ): string {
        KameleoonLogger::info(
            "Call of deprecated method 'getFeatureVariationKey'. " .
                "Please, use 'getVariation(\$visitorCode, \$featureKey, true)' instead."
        );
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->isFeatureActive(visitorCode: '%s', featureKey: '%s', timeout: %s, " .
                "isUniqueIdentifier: %s)",
            $visitorCode,
            $featureKey,
            $timeout,
            $isUniqueIdentifier,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        if ($isUniqueIdentifier !== null) {
            $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
        }
        [, $variationKey] = $this->getFeatureVariationKeyInternal($visitorCode, $featureKey, $timeout);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->isFeatureActive(visitorCode: '%s', featureKey: '%s', timeout: %s, " .
                "isUniqueIdentifier: %s) -> (variationKey: '%s')",
            $visitorCode,
            $featureKey,
            $timeout,
            $isUniqueIdentifier,
            $variationKey,
        );
        return $variationKey;
    }

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, true)`
     */
    public function getFeatureVariable(
        string $visitorCode,
        string $featureKey,
        string $variableName,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null
    ) {
        KameleoonLogger::info(
            "Call of deprecated method 'getFeatureVariable'. " .
                "Please, use 'getVariation(\$visitorCode, \$featureKey, true)' instead."
        );
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getFeatureVariable(visitorCode: '%s', featureKey: '%s', " .
                "variableName: '%s', timeout: %s, isUniqueIdentifier: %s)",
            $visitorCode,
            $featureKey,
            $variableName,
            $timeout,
            $isUniqueIdentifier,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        if ($isUniqueIdentifier !== null) {
            $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
        }
        [$featureFlag, $variationKey] = $this->getFeatureVariationKeyInternal($visitorCode, $featureKey, $timeout);
        $variation = $featureFlag->getVariation($variationKey);
        if (is_null($variation)) {
            KameleoonLogger::info(
                "RETURN: KameleoonClientImpl->getFeatureVariable(visitorCode: '%s', featureKey: '%s', " .
                    "variableName: '%s', timeout: %s, isUniqueIdentifier: %s) -> (variable: null)",
                $visitorCode,
                $featureKey,
                $variableName,
                $timeout,
                $isUniqueIdentifier,
            );
            return null;
        }
        $variable = $variation->getVariable($variableName);
        if (!is_null($variable)) {
            $value = $variable->getValue();
            KameleoonLogger::info(
                "RETURN: KameleoonClientImpl->getFeatureVariable(visitorCode: '%s', featureKey: '%s', " .
                    "variableName: '%s', timeout: %s, isUniqueIdentifier: %s) -> (variable: %s)",
                $visitorCode,
                $featureKey,
                $variableName,
                $timeout,
                $isUniqueIdentifier,
                $value,
            );
            return $value;
        } else {
            throw new FeatureVariableNotFound("Feature variable {$variableName} not found");
        }
    }

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, false)`
     */
    public function getFeatureVariationVariables(string $featureKey, string $variationKey, ?int $timeout = null): array
    {
        KameleoonLogger::info(
            "Call of deprecated method 'getFeatureVariationVariables'. " .
                "Please, use 'getVariation(\$visitorCode, \$featureKey, false)' instead."
        );
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getFeatureVariationVariables(featureKey: '%s', variationKey: '%s', " .
                "timeout: %s)",
            $featureKey,
            $variationKey,
            $timeout,
        );
        $this->loadConfiguration($timeout);
        $featureFlag = $this->dataManager->getDataFile()->getFeatureFlag($featureKey);
        $this->dataManager->getDataFile()->ensureEnvironmentEnabled($featureFlag);
        $variation = $featureFlag->getVariation($variationKey);
        if (is_null($variation)) {
            throw new FeatureVariationNotFound("Variation key {$variationKey} not found");
        }
        $variables = array_map(fn($var) => $var->getValue(), $variation->variables);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getFeatureVariationVariables(featureKey: '%s', variationKey: '%s', " .
                "timeout: %s) -> (variables: %s)",
            $featureKey,
            $variationKey,
            $timeout,
            $variables,
        );
        return $variables;
    }

    /**
     * @deprecated Please use `getDataFile` instead.
     */
    public function getFeatureList(?int $timeout = null): array
    {
        KameleoonLogger::info(
            "Call of deprecated method 'getFeatureList'. Please, use 'getDataFile' instead."
        );
        KameleoonLogger::info("CALL: KameleoonClientImpl->getFeatureList(timeout: %s)", $timeout);
        $this->loadConfiguration($timeout);
        $features = array_keys($this->dataManager->getDataFile()->getFeatureFlags());
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getFeatureList(timeout: %s) -> (features: %s)",
            $timeout,
            $features
        );
        return $features;
    }

    /**
     * @deprecated deprecated since version 4.3.0. Please use `getActiveFeatures`
     */
    public function getActiveFeatureListForVisitor(string $visitorCode, ?int $timeout = null): array
    {
        KameleoonLogger::info(
            "Call of deprecated method 'getActiveFeatureListForVisitor'. Please, use 'getActiveFeatures' instead."
        );
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getActiveFeatureListForVisitor(visitorCode: '%s', timeout: %s)",
            $visitorCode,
            $timeout,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $arrayKeys = array();
        $this->loadConfiguration($timeout);
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        foreach ($this->dataManager->getDataFile()->getFeatureFlags() as $featureFlag) {
            try {
                $evalExp = $this->evaluate($visitor, $visitorCode, $featureFlag, false, false);
            } catch (FeatureEnvironmentDisabled $ex) {
                continue;
            }
            $variationKey = $this->calculateVariationKey($evalExp, $featureFlag->defaultVariationKey);
            if ($variationKey != Variation::VARIATION_OFF) {
                $arrayKeys[] = $featureFlag->featureKey;
            }
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getActiveFeatureListForVisitor(visitorCode: '%s', timeout: %s)" .
                " -> (arrayKeys: %s)",
            $visitorCode,
            $timeout,
            $arrayKeys,
        );
        return $arrayKeys;
    }

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariations($visitorCode, true, false)`
     */
    public function getActiveFeatures(string $visitorCode, ?int $timeout = null): array
    {
        KameleoonLogger::info(
            "Call of deprecated method 'getActiveFeatures'. " .
                "Please, use 'getVariations(\$visitorCode, true, false)' instead."
        );
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getActiveFeatures(visitorCode: '%s', timeout: %s)",
            $visitorCode,
            $timeout,
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $mapActiveFeatures = array();
        $this->loadConfiguration($timeout);
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        foreach ($this->dataManager->getDataFile()->getFeatureFlags() as $featureFlag) {
            try {
                $evalExp = $this->evaluate($visitor, $visitorCode, $featureFlag, false, false);
            } catch (FeatureEnvironmentDisabled $ex) {
                continue;
            }
            $variationKey = $this->calculateVariationKey($evalExp, $featureFlag->defaultVariationKey);
            if ($variationKey == Variation::VARIATION_OFF) {
                continue;
            }
            $variation = $featureFlag->getVariation($variationKey);
            $mapActiveFeatures[$featureFlag->featureKey] = self::createExternalVariation($variation, $evalExp);
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getActiveFeatures(visitorCode: '%s', timeout: %s) -> (activeFeatures: %s)",
            $visitorCode,
            $timeout,
            $mapActiveFeatures
        );
        return $mapActiveFeatures;
    }

    public function getEngineTrackingCode(string $visitorCode): string
    {
        KameleoonLogger::info("CALL: KameleoonClientImpl->getEngineTrackingCode(visitorCode: '%s')", $visitorCode);
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        if (!isset($this->hybridManager)) {
            $this->hybridManager = new HybridManagerImpl($this->dataManager);
        }
        $trackingCode = $this->hybridManager->getEngineTrackingCode(
            !is_null($visitor) ? $visitor->getAssignedVariations() : null
        );
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getEngineTrackingCode(visitorCode: '%s') -> (trackingCode: %s)",
            $visitorCode,
            $trackingCode,
        );
        return $trackingCode;
    }

    public function getRemoteData(string $key, ?int $timeout = null)
    {
        KameleoonLogger::info("CALL: KameleoonClientImpl->getRemoteData(key: '%s', timeout: %s)", $key, $timeout);
        $remoteData = $this->getRemoteDataManager()->getData($key, $timeout);
        KameleoonLogger::info(
            function () use ($key, $timeout, $remoteData) {
                return sprintf(
                    "RETURN: KameleoonClientImpl->getRemoteData(key: '%s', timeout: %s) -> (remoteData: %s)",
                    $key,
                    $timeout,
                    json_encode($remoteData),
                );
            }
        );
        return $remoteData;
    }

    public function getRemoteVisitorData(
        string $visitorCode,
        ?int $timeout = null,
        bool $addData = true,
        ?RemoteVisitorDataFilter $filter = null,
        ?bool $isUniqueIdentifier = null
    ): array {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getRemoteVisitorData(visitorCode: '%s', timeout: %s, addData: %s, " .
                "filter: %s, isUniqueIdentifier: %s)",
            $visitorCode,
            $timeout,
            $addData,
            $filter,
            $isUniqueIdentifier,
        );
        $this->loadConfiguration($timeout);
        if ($isUniqueIdentifier !== null) {
            $this->setUniqueIdentifier($visitorCode, $isUniqueIdentifier);
        }
        $visitorData = $this->getRemoteDataManager()->getVisitorData($visitorCode, $timeout, $filter, $addData);
        KameleoonLogger::info(
            function () use ($visitorCode, $timeout, $addData, $filter, $isUniqueIdentifier, $visitorData) {
                return sprintf(
                    "RETURN: KameleoonClientImpl->getRemoteVisitorData(visitorCode: '%s', timeout: %s, addData: %s, " .
                        "filter: %s, isUniqueIdentifier: %s) -> (visitorData: %s)",
                    $visitorCode,
                    $timeout,
                    $addData,
                    $filter,
                    $isUniqueIdentifier,
                    json_encode($visitorData),
                );
            }
        );
        return $visitorData;
    }

    public function getVisitorWarehouseAudience(
        string $visitorCode,
        int $customDataIndex,
        ?string $warehouseKey = null,
        ?int $timeout = null
    ): ?CustomData {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->getVisitorWarehouseAudience(visitorCode: '%s', customDataIndex: %s, " .
                "warehouseKey: '%s', timeout: %s)",
            $visitorCode,
            $customDataIndex,
            $warehouseKey,
            $timeout,
        );
        if (!isset($this->warehouseManager)) {
            $this->warehouseManager = new WarehouseManagerImpl($this->networkManager, $this->visitorManager);
        }
        $customData = $this->warehouseManager->getVisitorWarehouseAudience(
            $visitorCode,
            $customDataIndex,
            $warehouseKey,
            $timeout
        );
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->getVisitorWarehouseAudience(visitorCode: '%s', customDataIndex: %s, " .
                "warehouseKey: '%s', timeout: %s) -> (customData: %s)",
            $visitorCode,
            $customDataIndex,
            $warehouseKey,
            $timeout,
            $customData,
        );
        return $customData;
    }

    public function setLegalConsent(string $visitorCode, bool $legalConsent): void
    {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->setLegalConsent(visitorCode: '%s', legalConsent: %s)",
            $visitorCode,
            $legalConsent
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->visitorManager->getOrCreateVisitor($visitorCode)->setLegalConsent(
            $legalConsent ? LegalConsent::GIVEN : LegalConsent::NOT_GIVEN
        );
        $this->cookieManager->update($visitorCode, $legalConsent);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->setLegalConsent(visitorCode: '%s', legalConsent: %s)",
            $visitorCode,
            $legalConsent
        );
    }

    /*
     * Helper Methods (Private Methods)
     */

    private function getFeatureVariationKeyInternal(
        string $visitorCode,
        string $featureKey,
        ?int $timeout = null
    ): array {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->getFeatureVariationKeyInternal(visitorCode: '%s', featureKey: '%s', " .
                "timeout: %s)",
            $visitorCode,
            $featureKey,
            $timeout,
        );
        $this->loadConfiguration($timeout);
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $featureFlag = $this->dataManager->getDataFile()->getFeatureFlag($featureKey);
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        $evalExp = $this->evaluate($visitor, $visitorCode, $featureFlag, true, true);
        $variationKey = $this->calculateVariationKey($evalExp, $featureFlag->defaultVariationKey);
        $this->trackingManager->trackVisitor($visitorCode);
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->getFeatureVariationKeyInternal(visitorCode: '%s', featureKey: '%s', " .
                "timeout: %s) -> (featureFlag: %s, variationKey: '%s')",
            $visitorCode,
            $featureKey,
            $timeout,
            $featureFlag,
            $variationKey,
        );
        return [$featureFlag, $variationKey];
    }

    private function calculateVariationKey(?EvaluatedExperiment $evalExp, string $defaultVariationKey): string
    {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->calculateVariationKey(evalExp: %s, defaultVariationKey: '%s')",
            $evalExp,
            $defaultVariationKey,
        );
        $variationKey = ($evalExp != null)
            ? $evalExp->getVarByExp()->variationKey
            : $defaultVariationKey;
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->calculateVariationKey(evalExp: %s, defaultVariationKey: '%s')" .
                " -> (variationKey: '%s')",
            $evalExp,
            $defaultVariationKey,
            $variationKey,
        );
        return $variationKey;
    }

    private function saveVariation(
        string $visitorCode,
        ?EvaluatedExperiment $evalExp,
        bool $track = true
    ): void {
        $experimentId = ($evalExp !== null) ? $evalExp->getExperiment()->id : null;
        $variationId = ($evalExp !== null) ? $evalExp->getVarByExp()->variationId : null;
        if (($experimentId === null) || ($variationId === null)) {
            return;
        }
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->saveVariation(visitorCode: '%s', evalExp: %s, track: %s)",
            $visitorCode,
            $evalExp,
            $track,
        );
        $ruleType = AssignedVariation::convertLiteralRuleTypeToEnum($evalExp->getRuleType());
        $asVariation = new AssignedVariation($experimentId, $variationId, $ruleType);
        $this->visitorManager->addData($visitorCode, $track, $asVariation);
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->saveVariation(visitorCode: '%s', evalExp: %s, track: %s)",
            $visitorCode,
            $evalExp,
            $track,
        );
    }

    private static function getCodeForHash(
        ?Visitor $visitor,
        string $visitorCode,
        ?int $bucketingCustomDataIndex
    ): string {
        if ($visitor === null) {
            return $visitorCode;
        }
        // 1. Try to use the bucketing custom data's value if bucketingCustomDataId is defined
        if ($bucketingCustomDataIndex !== null) {
            $bucketingCustomData = $visitor->getCustomData()[$bucketingCustomDataIndex] ?? null;
            if (($bucketingCustomData !== null) && !empty($bucketingCustomData->getValues())) {
                return $bucketingCustomData->getValues()[0];
            }
        }
        // 2. Use mappingIdentifier instead of visitorCode if it was set up
        $mappingIdentifier = $visitor->getMappingIdentifier();
        return ($mappingIdentifier === null) ? $visitorCode : $mappingIdentifier;
    }

    private function shouldDataFileBeUpdated(): bool
    {
        return !file_exists($this->configurationFilePath) ||
            (time() >= filemtime($this->configurationFilePath) + $this->clientConfig->getRefreshIntervalSecond());
    }

    private function getTimeout(?int $timeout = null): int
    {
        return $timeout ?? $this->clientConfig->getDefaultTimeoutMillisecond();
    }

    //load configuration if it was not loaded
    private function loadConfiguration(?int $timeout = null)
    {
        KameleoonLogger::debug("CALL: KameleoonClientImpl->loadConfiguration(timeout: %s)", $timeout);
        $dataFileShouldBeUpdated = $this->shouldDataFileBeUpdated();
        $dataFile = $this->dataManager->getDataFile();
        $dataFileEmpty = !file_exists($this->configurationFilePath);
        if (($dataFile == null) && !$dataFileEmpty) {
            try {
                $this->loadDataFileLocal();
            } catch (DataFileInvalid $e) {
                $this->updateConfigurationExclusively($this->getTimeout($timeout), true);
                $dataFileShouldBeUpdated = false;
            }
        }
        if ($dataFileShouldBeUpdated) {
            if ($dataFileEmpty) {
                $fp = fopen($this->configurationFilePath, "a");
                if ($fp !== false) {
                    fclose($fp);
                }
            }
            $this->updateConfigurationExclusively($this->getTimeout($timeout), $dataFileEmpty);
        }
        KameleoonLogger::debug("RETURN: KameleoonClientImpl->loadConfiguration(timeout: %s)", $timeout);
    }

    private function applyNewConfiguration($dataFileJson)
    {
        KameleoonLogger::debug("CALL: KameleoonClientImpl->applyNewConfiguration(dataFileJson: %s)", $dataFileJson);
        $this->dataManager->setDataFile(new DataFile($dataFileJson, $this->clientConfig->getEnvironment()));
        $settings = $this->dataManager->getDataFile()->getSettings();
        $this->networkManager->getUrlProvider()->applyDataApiDomain($settings->getDataApiDomain());
        KameleoonLogger::debug("RETURN: KameleoonClientImpl->applyNewConfiguration(dataFileJson: %s)", $dataFileJson);
    }

    private function loadDataFileLocal()
    {
        KameleoonLogger::debug("CALL: KameleoonClientImpl->loadDataFileLocal()");
        $dataFileJsonLocal = json_decode(file_get_contents($this->configurationFilePath, true));
        if ($dataFileJsonLocal === null) {
            throw new DataFileInvalid("Local data file is invalid");
        }
        $this->applyNewConfiguration($dataFileJsonLocal);
        KameleoonLogger::debug("RETURN: KameleoonClientImpl->loadDataFileLocal()");
    }

    private function updateConfigurationExclusively(...$args)
    {
        $fp = fopen($this->configurationFilePath, "r+");
        if ($fp === false) {
            KameleoonLogger::error("Cannot update configuration file");
            if (file_exists($this->configurationFilePath)) {
                $this->loadDataFileLocal();
            }
            throw new DataFileInvalid("Cannot read local configuration file");
        }
        try {
            if (flock($fp, LOCK_EX)) {
                $this->updateConfiguration(...$args);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    private function updateConfiguration(int $timeout, bool $forceNetworkRequest = false)
    {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->updateConfiguration(timeout: %s, forceNetworkRequest: %s)",
            $timeout,
            $forceNetworkRequest
        );
        $dataFile = $this->dataManager->getDataFile();
        $updated = false;
        try {
            clearstatcache(false, $this->configurationFilePath);
            if ($this->shouldDataFileBeUpdated() || $forceNetworkRequest) {
                $lastModified = ($dataFile !== null) ? $dataFile->getLastModified() : null;
                $fetchedConfiguration = $this->networkManager->fetchConfiguration($timeout, $lastModified);
                if (($fetchedConfiguration !== null) && !empty($fetchedConfiguration->configuration)) {
                    $dataFileJsonRemote = json_decode($fetchedConfiguration->configuration);
                    if (isset($dataFileJsonRemote->featureFlags)) {
                        if ($fetchedConfiguration->lastModified !== null) {
                            $dataFileJsonRemote->phpsdk_datafile_lastmod = $fetchedConfiguration->lastModified;
                        }
                        file_put_contents($this->configurationFilePath, json_encode($dataFileJsonRemote));
                        $this->applyNewConfiguration($dataFileJsonRemote);
                        $updated = true;
                    }
                }
            }
        } catch (Exception $e) {
            KameleoonLogger::error(
                "Saved data file will be used. The file needs to be updated, but an error occurred: " . $e->getMessage()
            );
        } finally {
            // Updating data file modification time in any case
            // to prevent recurring CC requests when the server is down.
            $this->updateConfigurationFileModificationTime();
        }
        if (!$updated && ($dataFile === null)) {
            $this->loadDataFileLocal();
        }
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->updateConfiguration(timeout: %s, forceNetworkRequest: %s)",
            $timeout,
            $forceNetworkRequest
        );
    }

    private function updateConfigurationFileModificationTime()
    {
        KameleoonLogger::debug("CALL: KameleoonClientImpl->updateConfigurationFileModificationTime()");
        if (file_exists($this->configurationFilePath)) {
            touch($this->configurationFilePath);
            clearstatcache(false, $this->configurationFilePath);
        }
        KameleoonLogger::debug("RETURN: KameleoonClientImpl->updateConfigurationFileModificationTime()");
    }

    private function evaluateCBScores(
        ?Visitor $visitor,
        string $visitorCode,
        ?Rule $rule,
        ?int $bucketingCustomDataIndex
    ): ?EvaluatedExperiment {
        if (($visitor === null) || ($visitor->getCBScores() === null)) {
            return null;
        }
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->evaluateCBScores(visitor, visitorCode: '%s', rule: %s, "
                . "bucketingCustomDataIndex: %s)",
            $visitorCode,
            $rule,
            $bucketingCustomDataIndex
        );
        $evalExp = null;
        $varIdGroupByScores = $visitor->getCBScores()->getValues()[$rule->experiment->id] ?? null;
        if ($varIdGroupByScores !== null) {
            $varByExpInCbs = null;
            foreach ($varIdGroupByScores as $varGroup) {
                // Finding varByExps which exist in CBS variation IDs
                $varByExpInCbs = [];
                foreach ($rule->experiment->variationsByExposition as $varByExp) {
                    if (in_array($varByExp->variationId, $varGroup->getIds())) {
                        $varByExpInCbs[] = $varByExp;
                    }
                }
                if (!empty($varByExpInCbs)) { // Skiping if not found any varByExp
                    break; // We need take only one list with the highest scores
                }
            }
            if (!empty($varByExpInCbs)) {
                $size = count($varByExpInCbs);
                if ($size > 1) { // if more than one varByExp for score -> randomly get
                    $codeForHash = self::getCodeForHash($visitor, $visitorCode, $bucketingCustomDataIndex);
                    $variationHash = Hasher::obtain($codeForHash, $rule->experiment->id, $rule->respoolTime);
                    KameleoonLogger::debug("Calculated CBS hash %s for code '%s'", $variationHash, $codeForHash);
                    $idx = (int)($variationHash * $size);
                    if ($idx >= $size) {
                        $idx = $size - 1;
                    }
                } else {
                    $idx = 0;
                }
                $evalExp = EvaluatedExperiment::fromVarByExpRule($varByExpInCbs[$idx], $rule);
            }
        }
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->evaluateCBScores(visitor, visitorCode: '%s', rule: %s, "
                . "bucketingCustomDataIndex: %s) -> (evalExp: %s)",
            $visitorCode,
            $rule,
            $bucketingCustomDataIndex,
            $evalExp
        );
        return $evalExp;
    }

    private function calculateVariationKeyForFeature(
        string $visitorCode,
        FeatureFlag $featureFlag
    ): ?EvaluatedExperiment {
        KameleoonLogger::debug(
            "CALL: KameleoonClientImpl->calculateVariationKeyForFeature(visitorCode: '%s', featureFlag: %s)",
            $visitorCode,
            $featureFlag
        );
        // use mappingIdentifier instead of visitorCode if it was set up
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        $arrayConsent = $this->getConsentAndBlockingBehaviour($visitor);
        $consent = $arrayConsent[0];
        $codeForHash = self::getCodeForHash($visitor, $visitorCode, $featureFlag->bucketingCustomDataIndex);
        $evalExp = null;
        // no rules -> return defaultVariationKey
        foreach ($featureFlag->rules as $rule) {
            $forcedVariation = ($visitor !== null)
                ? $visitor->getForcedExperimentVariation($rule->experiment->id) : null;
            if (($forcedVariation !== null) && $forcedVariation->isForceTargeting()) {
                // Forcing experiment variation in force-targeting mode
                $evalExp = EvaluatedExperiment::fromVarByExpRule($forcedVariation->getVarByExp(), $rule);
                break;
            }
            // check if visitor is targeted for rule, else next rule
            if (!$this->targetingManager->checkTargeting($visitorCode, $rule->experiment->id, $rule->segment)) {
                continue;
            }
            if ($forcedVariation !== null) {
                // Forcing experiment variation in targeting-only mode
                $evalExp = EvaluatedExperiment::fromVarByExpRule($forcedVariation->getVarByExp(), $rule);
                break;
            }
            // uses for rule exposition
            $hashRule = Hasher::obtain($codeForHash, $rule->id, $rule->respoolTime);
            KameleoonLogger::debug("Calculated rule hash %s for code '%s'", $hashRule, $codeForHash);
            // check main expostion for rule with hashRule
            if ($hashRule <= $rule->exposition) {
                // Checking if the evaluation is blocked due to the consent policy
                if (($consent == LegalConsent::NOT_GIVEN) && $rule->isExperiment()) {
                    $completelyBlockingBehaviour = $arrayConsent[1];
                    if ($completelyBlockingBehaviour) {
                        throw new FeatureEnvironmentDisabled(
                            "Evaluation of $rule is blocked because consent is not provided for visitor '$visitorCode'"
                        );
                    }
                    break;
                }
                // check main exposition for rule with hashRule
                $evalExp = $this->evaluateCBScores(
                    $visitor,
                    $visitorCode,
                    $rule,
                    $featureFlag->bucketingCustomDataIndex
                );
                if ($evalExp !== null) {
                    break;
                }
                if ($rule->isTargetedDelivery() && count($rule->experiment->variationsByExposition) > 0) {
                    $evalExp = EvaluatedExperiment::fromVarByExpRule(
                        $rule->experiment->variationsByExposition[0],
                        $rule
                    );
                    break;
                }
                // uses for variation's expositions
                $hashVariation = Hasher::obtain($codeForHash, $rule->experiment->id, $rule->respoolTime);
                KameleoonLogger::debug("Calculated variation hash %s for code '%s'", $hashVariation, $codeForHash);
                // get variation key with new hashVariation
                $variation = $rule->experiment->getVariationByHash($hashVariation);
                // variation can be null for experiment rules only, for targeted rule will be always exist
                if (!is_null($variation)) {
                    $evalExp = EvaluatedExperiment::fromVarByExpRule($variation, $rule);
                    break;
                }
            } elseif ($rule->isTargetedDelivery()) {
                // if visitor is targeted but not bucketed for targeted rule then break cycle -> return default
                break;
            }
        }
        KameleoonLogger::debug(
            "RETURN: KameleoonClientImpl->calculateVariationKeyForFeature(visitorCode: '%s', featureFlag: %s)" .
                " -> (evalExp: %s)",
            $visitorCode,
            $featureFlag,
            $evalExp
        );
        return $evalExp;
    }

    private function getConsentAndBlockingBehaviour(?Visitor $visitor): array
    {
        $dataFile = $this->dataManager->getDataFile();
        $behaviour = $dataFile->getSettings()->isCompletelyBlockedByConsent();

        $consent = LegalConsent::GIVEN;

        if ($dataFile->getSettings()->isConsentRequired()) {
            $consent = $visitor !== null
                ? $visitor->getLegalConsent()
                : LegalConsent::UNKNOWN;
        }

        return [$consent, $behaviour];
    }

    private function getRemoteDataManager(): RemoteDataManager
    {
        if (!isset($this->remoteDataManager)) {
            $this->remoteDataManager =
                new RemoteDataManagerImpl($this->dataManager, $this->networkManager, $this->visitorManager);
        }
        return $this->remoteDataManager;
    }

    private function setUniqueIdentifier(string $visitorCode, bool $isUniqueIdentifier): void
    {
        KameleoonLogger::info(
            "The 'isUniqueIdentifier' parameter is deprecated. Please, add 'UniqueIdentifier' to a visitor instead."
        );
        $this->visitorManager->addData($visitorCode, true, new Data\UniqueIdentifier($isUniqueIdentifier));
    }

    public function setForcedVariation(
        string $visitorCode,
        int $experimentId,
        ?string $variationKey,
        bool $forceTargeting = true,
        ?int $timeout = null
    ): void {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->setForcedVariation(visitorCode: '%s', experimentId: %s," .
                " variationKey: %s, forceTargeting: %s, timeout: %s)",
            $visitorCode,
            $experimentId,
            ($variationKey === null) ? "null" : "'$variationKey'",
            $forceTargeting,
            $timeout
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration($timeout);
        if ($variationKey !== null) {
            $ruleInfo = $this->dataManager->getDataFile()->getRuleInfoByExpId($experimentId);
            if ($ruleInfo === null) {
                throw new FeatureExperimentNotFound("Experiment $experimentId is not found");
            }
            $rule = $ruleInfo->getRule();
            $forcedVariation = new ForcedExperimentVariation(
                $rule,
                $rule->experiment->getVariationByKey($variationKey),
                $forceTargeting
            );
            $this->visitorManager->addData($visitorCode, true, $forcedVariation);
        } else {
            $visitor = $this->visitorManager->getVisitor($visitorCode);
            if ($visitor !== null) {
                $visitor->resetForcedExperimentVariation($experimentId);
            }
        }
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->setForcedVariations(visitorCode: '%s', experimentId: %s," .
                " variationKey: %s, forceTargeting: %s, timeout: %s)",
            $visitorCode,
            $experimentId,
            ($variationKey === null) ? "null" : "'$variationKey'",
            $forceTargeting,
            $timeout
        );
    }

    public function evaluateAudiences(string $visitorCode, ?int $timeout = null): void
    {
        KameleoonLogger::info(
            "CALL: KameleoonClientImpl->evaluateAudiences(visitorCode: '%s', timeout: %s)",
            $visitorCode,
            $timeout
        );
        VisitorCodeManager::validateVisitorCode($visitorCode);
        $this->loadConfiguration($timeout);
        $segments = [];
        foreach ($this->dataManager->getDataFile()->getAudienceTrackingSegments() as $segment) {
            if ($this->targetingManager->checkTargeting($visitorCode, null, $segment)) {
                $segments[] = new TargetedSegment($segment->getId());
            }
        }
        if (!empty($segments)) {
            $this->visitorManager->addData($visitorCode, true, ...$segments);
        }
        $this->trackingManager->trackVisitor($visitorCode, false, $timeout);
        KameleoonLogger::info(
            "RETURN: KameleoonClientImpl->evaluateAudiences(visitorCode: '%s', timeout: %s)",
            $visitorCode,
            $timeout
        );
    }

    public function getDataFile(?int $timeout = null): Types\DataFile
    {
        KameleoonLogger::info("CALL: KameleoonClientImpl->getDataFile()");
        $this->loadConfiguration($timeout);
        $dataFile = $this->dataManager->getExternalDataFile();
        KameleoonLogger::info("RETURN: KameleoonClientImpl->getDataFile() -> (dataFile: %s)", $dataFile);
        return $dataFile;
    }
}
