<?php

namespace Kameleoon;

use Kameleoon\Data\CustomData;
use Kameleoon\Types\RemoteVisitorDataFilter;
use Kameleoon\Types\Variation;
use Kameleoon\Types\DataFile;

interface KameleoonClient
{
    public function getVisitorCode(?string $defaultVisitorCode = null, ?int $timeout = null);

    public function addData($visitorCode, ...$data);

    public function flush(
        $visitorCode = null,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $instant = false
    );

    /**
     * The method to track conversion. You can add a conversion manually with
     * `addData` and `Data\Conversion` data object. The result will be same.
     *
     * @param string $visitorCode unique identifier of a visitor. This field is mandatory.
     * @param int $goalID ID of the goal. This field is mandatory.
     * @param float $revenue Revenue of the conversion. This field is optional (`0.0` by default).
     * @param ?bool $isUniqueIdentifier (Deprecated) Optional flag indicating whether the visitorCode is a unique
     * identifier; the default value is `null`.
     * @param bool $negative Defines if the revenue is positive or negative.
     * This field is optional (`false` by default).
     * @param ?array<Data\CustomData> $metadata Metadata of the conversion. This field is optional (`null` by default).
     */
    public function trackConversion(
        $visitorCode,
        int $goalID,
        float $revenue = 0.0,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $negative = false,
        ?array $metadata = null
    );

    /**
     * Checks if a feature is active for a given visitor.
     *
     * This method takes `visitorCode` and `featureKey` as mandatory arguments, and
     * `timeout`, `isUniqueIdentifier`, `track` as optional arguments.
     * It checks whether the specified feature flag is active for the visitor.
     *
     * If the visitor has not previously interacted with the feature flag, the SDK assigns a random
     * boolean value (`true` if the feature should be active, `false` if not). If the visitor has been
     * previously associated with the feature flag, the SDK retrieves the previously assigned value.
     *
     * Ensure that proper error handling is implemented in your code to manage potential exceptions.
     *
     * @param string $visitorCode The user's unique identifier.
     * @param string $featureKey The key of the feature you want to expose to a user.
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. This field is optional.
     * @param ?bool $isUniqueIdentifier (Deprecated) Optional flag indicating whether the visitorCode is a unique
     * identifier; the default value is `null`.
     * @param bool $track Optional flag indicating whether tracking of the feature evaluation is enabled (`true`) or
     * disabled (`false`); the default value is `true`.
     *
     * @return bool `true` if the feature flag is active for the given visitor, otherwise `false`.
     *
     * @throws Exception\VisitorCodeInvalid Thrown if the provided `visitorCode` is invalid
     * (e.g., empty or exceeds 255 characters).
     * @throws Exception\FeatureNotFound Thrown if the requested
     * feature key has not been found in the internal configuration of the SDK.
     */
    public function isFeatureActive(
        string $visitorCode,
        string $featureKey,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null,
        bool $track = true
    ): bool;

    /**
     * Retrieves the variation assigned to the given visitor for a specific feature flag.
     *
     * This method takes a `visitorCode` and `featureKey` as mandatory arguments, and `track` and `timeout` as
     * optional arguments. It returns the variation assigned to the visitor. If
     * the visitor is not associated with any feature flag rules, the method returns the default
     * variation for the given feature flag.
     *
     * Ensure that proper error handling is implemented in your code to manage potential exceptions.
     *
     * @param string $visitorCode The unique identifier of the visitor.
     * @param string $featureKey The unique identifier of the feature flag.
     * @param bool $track Optional flag indicating whether tracking of the feature evaluation is enabled (`true`) or
     * disabled (`false`); the default value is `true`.
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. This field is optional.
     *
     * @return Types\Variation The variation assigned to the visitor if the visitor is associated with some rule of
     * the feature flag, otherwise the method returns the default variation of the feature flag.
     *
     * @throws Exception\VisitorCodeInvalid Thrown if the provided `visitorCode` is invalid
     * (e.g., empty or exceeds 255 characters).
     * @throws Exception\FeatureNotFound Thrown if the requested
     * feature key has not been found in the internal configuration of the SDK.
     * @throws Exception\FeatureEnvironmentDisabled Thrown if the requested feature flag is
     * disabled in the current environment.
     */
    public function getVariation(
        string $visitorCode,
        string $featureKey,
        bool $track = true,
        ?int $timeout = null
    ): Variation;

    /**
     * Retrieves an array of variations assigned to a given visitor across all feature flags.
     *
     * This method iterates over all available feature flags and returns the assigned variation
     * for each flag associated with the specified visitor. It takes `visitorCode` as a mandatory
     * argument, while `onlyActive`, `track`, `timeout` are optional.
     *
     * Ensure that proper error handling is implemented in your code to manage potential exceptions.
     *
     * @param string $visitorCode The unique identifier of the visitor.
     * @param bool $onlyActive Optional flag indicating whether to return only variations for active feature
     * flags (`true`) or for any feature flags (`false`); the default value is `false`.
     * @param bool $track Optional flag indicating whether tracking of the feature evaluation is enabled (`true`) or
     * disabled (`false`); the default value is `true`.
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. This field is optional.
     *
     * @return array<string, Types\Variation> An array consisting of feature flag keys as keys and their corresponding
     * variations (or the default variation of that feature flag) as values.
     *
     * @throws Exception\VisitorCodeInvalid Thrown if the provided `visitorCode` is invalid
     * (e.g., empty or exceeds 255 characters).
     */
    public function getVariations(
        string $visitorCode,
        bool $onlyActive = false,
        bool $track = true,
        ?int $timeout = null
    ): array;

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, true)`
     */
    public function getFeatureVariationKey(
        string $visitorCode,
        string $featureKey,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null
    );

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, true)`
     */
    public function getFeatureVariable(
        string $visitorCode,
        string $featureKey,
        string $variableName,
        ?int $timeout = null,
        ?bool $isUniqueIdentifier = null
    );

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariation($visitorCode, $featureKey, false)`
     */
    public function getFeatureVariationVariables(string $featureKey, string $variationKey, ?int $timeout = null): array;

    /**
     * @deprecated Please use `getDataFile` instead.
     */
    public function getFeatureList(?int $timeout = null): array;

    /**
     * @deprecated deprecated since version 4.3.0. Please use `getActiveFeatures`
     */
    public function getActiveFeatureListForVisitor(string $visitorCode, ?int $timeout = null): array;

    /**
     * @deprecated deprecated since version 4.5.0. Please use `getVariations($visitorCode, true, false)`
     */
    public function getActiveFeatures(string $visitorCode, ?int $timeout = null): array;

    public function getEngineTrackingCode(string $visitorCode): string;

    public function getRemoteData(string $key, ?int $timeout = null);

    public function getRemoteVisitorData(
        string $visitorCode,
        ?int $timeout = null,
        bool $addData = true,
        ?RemoteVisitorDataFilter $filter = null,
        ?bool $isUniqueIdentifier = null
    ): array;

    /**
     * Sets or updates the legal consent status for a visitor identified by their unique visitor code,
     * without affecting values in the response cookies based on the consent status.
     *
     * This method allows you to set or update the legal consent status for a specific visitor
     * identified by their visitor code and adjust values in the response cookies accordingly. The legal
     * consent status is represented by a boolean value, where 'true' indicates consent, and 'false'
     * indicates a withdrawal or absence of consent.
     *
     * Usage example:
     *
     * ```
     * // Set legal consent for a specific visitor and adjust cookie values accordingly
     * $kameeloonClient->setLegalConsent("visitor123", true);
     *
     * // Update legal consent for another visitor and modify cookie values based on the consent
     * // status
     * $kameeloonClient->setLegalConsent("visitor456", false);
     * ```
     *
     * @param string $visitorCode The unique visitor code identifying the visitor.
     * @param bool $legalConsent A boolean value representing the legal consent status.
     *                           - 'true' indicates the visitor has given legal consent.
     *                           - 'false' indicates the visitor has withdrawn or not provided legal consent.
     * @throws Exception\VisitorCodeInvalid Throws when the provided visitor code is not valid (empty, or longer
     *                            than 255 characters)
     */
    public function setLegalConsent(string $visitorCode, bool $legalConsent): void;

    /**
     * Retrieves data associated with a visitor's warehouse audiences and adds it to the visitor.
     *
     * Retrieves all audience data associated with the visitor in your data warehouse using the
     * specified `$visitorCode` and `$warehouseKey`. The `$warehouseKey` is typically your internal user
     * ID. The `$customDataIndex` parameter corresponds to the Kameleoon custom data that Kameleoon uses
     * to target your visitors. You can refer to the warehouse targeting documentation for additional details.
     * The method returns a CustomData object, confirming that the data has been added to the visitor
     * and is available for targeting purposes.
     *
     * @param string $visitorCode The unique identifier of the visitor for whom you want to retrieve and add the data.
     * @param int $customDataIndex An integer representing the index of the custom data you want to use to target your
     * BigQuery Audiences.
     * @param ?string $warehouseKey The key to identify the warehouse data, typically your internal user ID.
     * The value is optional.
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. This field is optional.
     * @return ?CustomData CustomData is not null in case if it was sucessfully added to the visitor. Otherwise, null.
     * @throws Exception\VisitorCodeInvalid Throws when the provided visitor code is not valid (empty, or longer
     *                            than 255 characters)
     */
    public function getVisitorWarehouseAudience(
        string $visitorCode,
        int $customDataIndex,
        ?string $warehouseKey = null,
        ?int $timeout = null
    ): ?CustomData;

    /**
     * Sets or resets a forced variation for a visitor in a specific experiment,
     * so the experiment will be evaluated to the variation for the visitor.
     *
     * In order to reset the forced variation set the `$variationKey` parameter to `null`.
     * If the forced variation you want to reset does not exist, the method will have no effect.
     *
     * @param string $visitorCode The unique visitor code identifying the visitor.
     * @param int $experimentId The identifier of the experiment you want to set/reset the forced variation for.
     * @param ?string $variationKey The identifier of the variation you want the experiment to be evaluated to.
     * Set to `null` to reset the forced variation.
     * @param bool $forceTargeting If `true`, the visitor will be targeted to the experiment regardless its conditions.
     * Otherwise, the normal targeting logic will be preserved. Optional (defaults to `true`).
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. Optional (defaults to `null`).
     * @throws Exception\VisitorCodeInvalid The provided **visitor code** is invalid.
     * @throws Exception\FeatureExperimentNotFound The provided **experiment id** does not exist in the feature flag.
     * @throws Exception\FeatureVariationNotFound The provided **variation key** does not belong to the experiment.
     */
    public function setForcedVariation(
        string $visitorCode,
        int $experimentId,
        ?string $variationKey,
        bool $forceTargeting = true,
        ?int $timeout = null
    ): void;

    /**
     * Evaluates the visitor against all available Audiences Explorer segments and tracks those that match.
     * A detailed analysis of segment performance can then be performed directly in Audiences Explorer.
     *
     * @param string $visitorCode The unique visitor code identifying the visitor.
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. Optional (defaults to `null`).
     * @throws Exception\VisitorCodeInvalid The provided **visitor code** is invalid.
     */
    public function evaluateAudiences(string $visitorCode, ?int $timeout = null): void;

    /**
     * Retrieves the current SDK configuration (also known as the data file),
     * containing all feature flags and their variations.
     *
     * @param ?int $timeout This parameter specifies the maximum amount of time the method can block to wait for a
     * result. Optional (defaults to `null`).
     * @return Types\DataFile The current SDK configuration.
     */
    public function getDataFile(?int $timeout = null): DataFile;
}
