# Changelog
All notable changes to this project will be documented in this file.

## 4.21.0 - 2026-04-22
### Features
* Refactored code to ensure compatibility with [PHP 8.5](https://www.php.net/ChangeLog-8.php#8.5.5)
* Added support for variation simulation when feature flags are **inactive (OFF state)** across the following methods:
  - [`getVariation`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariation)
  - [`getVariations`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariations)
  - [`isFeatureActive`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#isfeatureactive)
  - This support is also extended to the following **deprecated methods**:
    - [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getactivefeaturelistforvisitor)
    - [`getActiveFeatures`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getactivefeatures)
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariationkey)
    - [`getFeatureVariable`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariable)
* Improved the performance of the [`getDataFile()`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getdatafile) method. It now returns a cached [`DataFile`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#datafile) instance that is refreshed whenever the SDK configuration is updated.
* Added a new `dateModified` property to the [`DataFile`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#datafile) object returned by the [`getDataFile`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getdatafile) method.
* The [`getFeatureList`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturelist) method has been deprecated in favor of `getDataFile()`.

## 4.20.0 - 2026-02-13
### Features
* Introduced support for the Kameleoon [`ApplicationVersion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#applicationversion) data type, allowing the visitor’s application version to be set and used in targeting evaluation.

## 4.19.0 - 2026-01-29
> [!CAUTION]
> This is the final notice regarding the legacy [cron job script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job). According to our [EOL policy](https://www.kameleoon.com/eol-policy), versions earlier than **4.4.0** have been end of life for more than one year.

> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Introduced a new `track` parameter for [`addData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#adddata). When set to `false`, the data is stored locally and used only for targeting evaluation; it is not sent to the Data API, helping to prevent duplicate data from being recorded. The default value is `true`. This behavior is consistent with the `track` parameter used in evaluation methods such as [`getVariation`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariation).

## 4.18.0 - 2025-12-24
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Introduced a new [`getDataFile`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getdatafile) method. This method returns the current SDK configuration (also known as the **data file**) used for evaluation and targeting. It is **not** intended for production use to fetch variations for every feature flag in the returned list, as it is not optimized for performance. For that purpose, use [`getVariations`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariations) instead. `getDataFile` is mainly useful for debugging or QA, for example to let internal users manually select a variant for a specific feature flag in production.
### Bug fixes
* Fixed an issue where **legal consent** was not taken into account during holdout evaluation.

## 4.17.0 - 2025-11-21
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Updated evaluation and tracking logic to comply with GDPR requirements when consent is not given:
    - If behavior is **partially blocked**, the default variation will be returned.
    - If behavior is **completely blocked**, an exception will be thrown.

## 4.16.1 - 2025-10-31
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* Fixed an issue where [`Conversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#conversion)'s metadata initialized with a name was not tracked.
* Made parameter `title` of [`PageView`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#pageview) optional.

## 4.16.0 - 2025-08-29
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added an `overwrite` flag to [`CustomData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#customdata), used as the `overwrite` parameter during tracking.
* [`CustomData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#customdata) can now be created using a `name`, in addition to the existing method of using an `index`.
### Bug fixes
* Renamed the `id` parameter to `index` for [`CustomData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#customdata). The previous name `id` could be mistakenly understood as a unique `CustomData` identifier, whereas the SDK actually requires an `index`.

## 4.15.0 - 2025-07-23
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added the [`evaluateAudiences`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#evaluateaudiences) method. This method iterates over all Audiences Explorer segments, evaluates each one, and tracks the segments for which the visitor is targeted using the [`TARGETINGSEGMENT`](https://developers.kameleoon.com/apis/data-api-rest/all-endpoints/post-visit-events/) event.

## 4.14.1 - 2025-06-30
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* A `KameleoonException` may be thrown during `KameleoonClient` initialization if the SDK is unable to access the **Kameleoon working directory**.

## 4.14.0 - 2025-06-27
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added support for using a Custom Data value for traffic bucketing instead of the default visitor code. [Learn More](https://help.kameleoon.com/create-feature-flag/#Advanced_Flag_Settings).

## 4.13.2 - 2025-06-10
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* Fixed duplicate slashes in file paths within the **Kameleoon work directory**.

## 4.13.1 - 2025-06-09
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* Fixed an issue where file paths in the **Kameleoon work directory** were malformed when the `kameleoon_work_dir` property did not include a trailing slash.
* Removed the Python dependency from the [cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job).
* Fixed an issue where several PHP processes sharing the same storage could each update the local client-config sequentially, forming a queue.
* Fixed an issue where an SDK response could be delayed during an OAuth endpoint outage, caused by requesting an access token on every call to methods sending tracking data.

## 4.13.0 - 2025-05-26
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added support for **304 (Not Modified)** responses from the SDK config service to avoid redundant updates and reduce traffic when the configuration hasn't changed.
* Added support for a **New**/**Returning** visitor breakdown filter in reports (requires calling [`getRemoteVisitorData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getremotevisitordata)).
### Bug fixes
* Fixed an issue where visitor data fields - [`Browser`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#browser), [`Device`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#device), and [`OperatingSystem`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#operatingsystem) - were all retrieved from the Data API and added to the visitor, even when only a subset of them was requested.

## 4.12.1 - 2025-04-08
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
* Changed the order in which **conversion** and **experiment** events are sent. This may lead to more accurate **visit**-level experiment reporting.

## 4.12.0 - 2025-04-02
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added support for new conditions:
    - Exclusive Campaign
    - Experiment
    - Personalization

## 4.11.0 - 2025-03-24
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added new optional parameters `negative` and `metadata` to the [`trackConversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#trackconversion) method.
* Added new optional parameter `metadata` to the [`Conversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#conversion) data constructor.

## 4.10.0 - 2025-03-18
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added support for Contextual Bandit evaluations. Calling [`getRemoteVisitorData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getremotevisitordata) with the `cbs=true` flag is required for this feature to function correctly. Platform-wide release expected in March 2025.

## 4.9.2 - 2025-03-06
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* Fixed an issue where several PHP processes sharing the same storage could each update the local client-config sequentially, forming a queue.
* Fixed an issue where an SDK response could be delayed during an OAuth endpoint outage, caused by requesting an access token on every call to methods sending tracking data.

## 4.9.1 - 2025-03-06
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug fixes
* Fixed an issue where the `revenue` parameter provided to [`trackConversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#trackconversion) was being ignored and not applied to the visitor's conversions.

## 4.9.0 - 2025-02-26
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added SDK support for **Mutually Exclusive Groups**. When feature flags are grouped into a **Mutually Exclusive Group**, only one flag in the group will be evaluated at a time. All other flags in the group will automatically return their default variation.
* Added new configuration parameter `networkDomain` (`network_domain`) to [`KameleoonClientConfig`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#createwithconfig) and the configuration file. This parameter allows specifying a custom domain for all outgoing network requests.

## 4.8.0 - 2025-02-10
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added SDK support for **holdout experiments**. Visitors assigned to a holdout experiment are excluded from all other rollouts and experiments, and consistently receive the default variation. For visitors not in a holdout experiment, the standard evaluation process applies, allowing them to be evaluated for all feature flags as usual. Platform-wide release expected in February 2025.
### Bug fixes
* Fixed an issue in the [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getactivefeaturelistforvisitor) method where feature flags disabled for the environment were not being filtered out.

## 4.7.0 - 2024-12-19
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added support for **simulated** variations.
* Added the [`setForcedVariation()`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#setforcedvariation) method. This method allows explicitly setting a forced variation for a visitor, which will be applied during experiment evaluation.

## 4.6.1 - 2024-11-20
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Bug Fixes
* Resolved an issue where the validation of [top-level domains](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#additional-configuration) for `localhost` resulted in incorrect failures. The SDK now accepts the provided domain without modification if it is deemed invalid and logs an [error](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#log-levels) to notify you of any issues with the specified domain.

## 4.6.0 - 2024-11-14
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Added new optional parameter `instant` to the [`flush`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#flush) method.
* Introduced a new `visitorCode` parameter to [`RemoteVisitorDataFilter`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#using-parameters-in-getremotevisitordata). This parameter determines whether to use the `visitorCode` from the most recent previous visit instead of the current `visitorCode`. When enabled, this feature allows visitor exposure to be based on the retrieved `visitorCode`, facilitating [cross-device reconciliation](https://developers.kameleoon.com/core-concepts/cross-device-experimentation/). Default value of the parameter is `true`.
### Bug Fixes
* Addressed an issue in the [`trackConversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#trackconversion) method where the `revenue` parameter was not correctly applied during conversion tracking.
* Fixed an issue with the [`Page URL`](https://developers.kameleoon.com/feature-management-and-experimentation/using-visit-history-in-feature-flags-and-experiments/#benefits-of-calling-getremotevisitordata) and [`Page Title`](https://developers.kameleoon.com/feature-management-and-experimentation/using-visit-history-in-feature-flags-and-experiments/#benefits-of-calling-getremotevisitordata) targeting conditions, where the condition evaluated all previously visited URLs in the session instead of only the current URL, corresponding to the latest added [`PageView`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#pageview).<br/>
**NOTE**: This change may impact your existing targeting. Please review your targeting conditions to ensure accuracy.

## 4.5.0 - 2024-10-11
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Introduced new evaluation methods for clarity and improved efficiency when working with the SDK:
  - [`getVariation`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariation)
  - [`getVariations`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvariations)
* These methods replace the deprecated ones:
  - [`getFeatureVariationKey`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getfeaturevariationkey)
  - [`getFeatureVariable`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getfeaturevariable)
  - [`getActiveFeatures`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getactivefeatures)
  - [`getFeatureVariationVariables`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getfeaturevariationvariables)
* A new version of the [`isFeatureActive`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#isfeatureactive) method now includes an optional `track` parameter, which controls whether the assigned variation is tracked (default: `true`).

## 4.4.0 - 2024-10-04
> [!WARNING]
> The [Cron job processing script](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#installing-the-cron-job) was updated. If you are upgrading from a version prior to 4.4.0, please ensure that you are using the latest version of the script.
### Features
* Improved the tracking mechanism to consolidate multiple visitors into a single request. The new approach combines information on all affected visitors into one request.
* Enhanced [logging](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#logging):
  - Introduced [log levels](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#log-levels):
    - `NONE`
    - `ERROR`
    - `WARNING`
    - `INFO`
    - `DEBUG`
  - Added support for [custom logger](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#custom-handling-of-logs) implementations.
* Enhanced top-level domain validation within the SDK. The implementation now includes automatic trimming of extraneous symbols and provides a [warning](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#log-levels) when an invalid domain is detected.
* Enhanced the [`getEngineTrackingCode`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getenginetrackingcode) method to properly handle `JS` and `CSS` variables.
* New Kameleoon Data type [`UniqueIdentifier`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#uniqueidentifier) is introduced. It will be used in all methods instead of `isUniqueIdentifier` parameter:
    - [`flush`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#flush)
    - [`trackConversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#trackconversion)
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getfeaturevariationkey)
    - [`getFeatureVariable`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getfeaturevariable)
    - [`isFeatureActive`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#isfeatureactive)
    - [`getRemoteVisitorData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getremotevisitordata)

## 4.3.0 - 2024-06-21
### Features
* The [Likelihood to convert](https://developers.kameleoon.com/feature-management-and-experimentation/using-visit-history-in-feature-flags-and-experiments) targeting condition is now available. Pre-loading the data is required using [`getRemoteVisitorData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getremotevisitordata) with the `kcs` parameter set to `true`.
* Added [`getActiveFeatures`](https://developers.kameleoon.com/php-sdk.html#getactivefeatures) method. It retrieves information about the active feature flags that are available for a specific visitor code. This method replaces the deprecated [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getactivefeaturelistforvisitor) method.

## 4.2.0 - 2024-05-22

### Features
* New targeting conditions are now available (some of them may require [`getRemoteVisitorData`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#getremotevisitordata) pre-loaded data)
  - Browser Cookie
  - Operating System
  - IP Geolocation
  - Kameleoon Segment
  - Target Feature Flag
  - Previous Page
  - Number of Page Views
  - Time since First Visit
  - Time since Last Visit
  - Number of Visits Today
  - Total Number of Visits
  - New or Returning Visitor
* New Kameleoon Data types were introduced:
  - [`Cookie`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#cookie)
  - [`OperatingSystem`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#operatingsystem)
  - [`Geolocation`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk#geolocation)
### Bug fixes
* Stability and performance improvements

## 4.1.0 - 2024-02-22
### Features
* Added support for additional Data API servers across the world for even faster network requests.
* Increased limit for requests to Data API: [rate limits](https://developers.kameleoon.com/apis/data-api-rest/overview/#rate-limits)
* Added [`getVisitorWarehouseAudience`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvisitorwarehouseaudience) method to retrieve all data associated with a visitor's warehouse audiences and adds it to the visitor.

## 4.0.1 - 2023-12-06
### Bug fixes
* Stability and performance improvements

## 4.0.0 - 2023-11-22
### Breaking changes
* Removed all methods and exceptions related to **experiments**:
    - `triggerExperiment`
    - `getVariationAssociatedData` (`obtainVariationAssociatedData`)
    - `getExperimentList`
    - `getExperimentListForVisitor`
    - `ExperimentConfigurationNotFound`
    - `NotTargeted`
    - `NotAllocated`
* Removed methods that were deprecated in 3.x versions:
    - `activateFeature`
    - `obtainVisitorCode`
    - `retrieveDataFromRemoteSource`
* Renamed classes, methods and exceptions:
    - `getFeatureAllVariables` to [`getFeatureVariationVariables`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariationvariables)
    - `ConfigurationNotLoaded` to `DataFileInvalid`
    - `CredentialsNotFound` to `ConfigCredentialsInvalid`
    - `FeatureConfigurationNotFound` to `FeatureNotFound`
    - `VariationConfigurationNotFound` to `FeatureVariationNotFound`
    - `VisitorCodeNotValid` to `VisitorCodeInvalid`
* Changes in external [configuration](https://developers.kameleoon.com/php-sdk.html#additional-configuration) file:
    - renamed `actions_configuration_refresh_interval` to `refresh_interval_minute`
    - renamed `default_timeout` to `default_timeout_millisecond`
* Added new exception [`SiteCodeIsEmpty`] for method [`KameleoonClientFactory::create`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#create) indicating that the provided sitecode is empty.
* Added new exception [`FeatureEnvironmentDisabled`] indicating that the feature flag is disabled for certain environments. The following methods can throw the new exception:
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariationkey)
    - [`getFeatureVariable`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariable)
    - [`getFeatureVariationVariables`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getfeaturevariationvariables)
* A new exception, `DataFileInvalid`, is thrown when the configuration has not been initialized. A missing configuration prevents the SDK from functioning properly. The `DataFileInvalid` exception can be thrown by the following methods:
    - [`flush`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#flush)
    - [`trackConversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#trackconversion)
* Removed `topLevelDomain` parameter from [`getVisitorCode`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#getvisitorcode). It should be provided with [`KameleoonClientConfig`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#createwithconfig) or via configuration file.

### Features
* Added **KameleoonClientConfig**, which can be used as parameter during initialization of a client in new method [`KameleoonClientFactory::createWithConfig`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#createwithconfig)
* Added [`setLegalConsent`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#setlegalconsent) method to determine the types data Kameleoon includes in tracking requests. This helps you adhere to legal and regulatory requirements while responsibly managing visitor data. You can find more information in the [Consent management policy](https://help.kameleoon.com/consent-management-policy/).

### Bug fixes
* Fixed an issue where using debug mode would result in a "Error: Uncaught TypeError: rawurlencode() expects parameter 1 to be string, null given" error message whenever the `$_SERVER["HTTP_USER_AGENT"]` environment variable wasn't set.

## 3.3.1 - 2024-02-02
### Bug fixes
* Fixed an issue where evaluating some targeting conditions would result in a exception.

## 3.3.0 - 2023-09-01
### Features
* Added a method to fetch a visitor's remote data (with an option to add the data to the visitor):
    - [`getRemoteVisitorData`](https://developers.kameleoon.com/php-sdk.html#getRemoteVisitorData)
### Bug fixes
* Fixed an issue where using the [`triggerExperiment`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#triggerexperiment) method might inadvertently include visitors in experiment results for experiments that they were supposed to be excluded from.

## 3.2.1 - 2023-08-30
### Bug fixes
* Fixed an issue where tracking requests were not sent.

## 3.2.0 - 2023-08-15 `[Deprecated due critical issues]`
### Features
* [`Browser`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#browser) data class now accepts a version number for the visitor's browser
* Added new conditions for targeting:
    - Visitor Code
    - SDK Language
    - [Page Title & Page Url](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#pageview)
    - [`Browser`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#browser)
    - [`Device`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#device)
    - [`Conversion`](https://developers.kameleoon.com/feature-management-and-experimentation/web-sdks/php-sdk/#trackconversion)
* A new exception, ConfigurationNotLoaded, is thrown when the configuration has not been initialized. A missing configuration prevents the SDK from functioning properly. The ConfigurationNotLoaded exception can be thrown by the following methods:
    - [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerExperiment)
    - [`isFeatureActive`](https://developers.kameleoon.com/php-sdk.html#isFeatureActive)
    - [`getVariationAssociatedData`](https://developers.kameleoon.com/php-sdk.html#getVariationAssociatedData)
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariationKey)
    - [`getFeatureVariable`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariable)
    - [`getFeatureAllVariables`](https://developers.kameleoon.com/php-sdk.html#getFeatureAllVariables)
    - [`getExperimentList`](https://developers.kameleoon.com/php-sdk.html#getExperimentList)
    - [`getExperimentListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getExperimentListForVisitor)
    - [`getFeatureList`](https://developers.kameleoon.com/php-sdk.html#getFeatureList)
    - [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getActiveFeatureListForVisitor)

## 3.1.0 - 2023-04-17
* The option **default_timeout** has been added to allow overriding the default timeout parameter specified in the configuration file. Additionally, the default timeout has been reduced to **5 seconds**.
* An optional timeout parameter has been added for methods. If no timeout is specified, the SDK will use the default timeout value:
    - [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerExperiment)
    - [`isFeatureActive`](https://developers.kameleoon.com/php-sdk.html#isFeatureActive)
    - [`getVariationAssociatedData`](https://developers.kameleoon.com/php-sdk.html#getVariationAssociatedData)
    - [`getRemoteData`](https://developers.kameleoon.com/php-sdk.html#getRemoteData)
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariationKey)
    - [`getFeatureVariable`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariable)
    - [`getFeatureAllVariables`](https://developers.kameleoon.com/php-sdk.html#getFeatureAllVariables)
    - [`getExperimentList`](https://developers.kameleoon.com/php-sdk.html#getExperimentList)
    - [`getExperimentListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getExperimentListForVisitor)
    - [`getFeatureList`](https://developers.kameleoon.com/php-sdk.html#getFeatureList)
    - [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getActiveFeatureListForVisitor)
* Added a new method:
    - [`getEngineTrackingCode`](https://developers.kameleoon.com/php-sdk.html#getenginetrackingcode) which can be used to simplify utilization of hybrid mode
* Renaming of methods:
    - `retrieveDataFromRemoteSource` -> [`getRemoteData`](https://developers.kameleoon.com/php-sdk.html#getRemoteData)
* Added possibility for [`CustomData`](https://developers.kameleoon.com/python-sdk.html#customdata) to use variable argument list of values

## 3.0.0 - 2023-02-02
* Added support of new feature flag rules:
    - [`getFeatureVariationKey`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariationKey)
    - `obtainFeatureVariable` -> [`getFeatureVariable`](https://developers.kameleoon.com/php-sdk.html#getFeatureVariable)
    - [`getFeatureAllVariables`](https://developers.kameleoon.com/php-sdk.html#getFeatureAllVariables)
    - `activateFeature` -> [`isFeatureActive`](https://developers.kameleoon.com/php-sdk.html#isFeatureActive)
* Methods added for obtaining experiment and feature flag lists:
    - [`getExperimentList`](https://developers.kameleoon.com/php-sdk.html#getExperimentList)
    - [`getExperimentListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getExperimentListForVisitor)
    - [`getFeatureList`](https://developers.kameleoon.com/php-sdk.html#getFeatureList)
    - [`getActiveFeatureListForVisitor`](https://developers.kameleoon.com/php-sdk.html#getActiveFeatureListForVisitor)
* Renaming:
    - `obtainVisitorCode` -> [`getVisitorCode`](https://developers.kameleoon.com/php-sdk.html#getVisitorCode)
    - `obtainVariationAssociatedData` -> [`getVariationAssociatedData`](https://developers.kameleoon.com/php-sdk.html#getVariationAssociatedData)
    - `NotActivated` -> `NotAllocated`
* Changes in Kameleoon Data:
    - Added possibility to set [`UserAgent`](https://developers.kameleoon.com/php-sdk.html#useragent).
* Removed blocking mode of SDK.
* Added support of `is among the values` operator for Custom Data

## 2.1.6 - 2022-11-03
* Fixed issue when SDK fetches configuration on every API request

## 2.1.5 - 2022-08-16
* Added support of PHP 7.x versions

## 2.1.4 - 2022-08-11
* Fixed crash on [`KameleoonClientFactory.create`](https://developers.kameleoon.com/php-sdk.html#create) method.

## 2.1.3 - 2022-06-27
* Significantly improved configuration load time
* Added update campaigns and feature flag configurations instantaneously with Real-Time Streaming Architecture: [`documentation`](https://developers.kameleoon.com/php-sdk.html#streaming) or [`product updates`](https://www.kameleoon.com/en/blog/real-time-streaming)
* Fixed issue when already associated visitor could get a new variation from [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment)
* Added support for **Experiment** & **Exclusive Campaign** conditions. Related to [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment)

## 2.1.2 - 2022-06-14
* Fixed an issue when tracking data could be sent twice. Related to: [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature), [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment) methods.

## 2.1.1 - 2022-05-16
* Added KameleoonData [`Device`](https://developers.kameleoon.com/php-sdk.html#device) data. Possible values are: **PHONE**, **TABLET**, **DESKTOP**.
* Removed KameleoonData `Interest`
* Fixed security issue

## 2.1.0 - 2022-04-12
* Added method for retrieving data from remote source: [`retrieveDataFromRemoteSource`](https://developers.kameleoon.com/php-sdk.html#retrievedatafromremotesource)

## 2.0.9 - 2022-02-15
* Added support of multi-environment for feature flags, Related to [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature), [`obtainFeatureVariable`](https://developers.kameleoon.com/php-sdk.html#obtainfeaturevariable)
* Fixed issue when [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature) returns wrong result


## 2.0.8 - 2022-01-25
* Added scheduling functionality for [`activateFeature`](https://developers.kameleoon.com/swift-sdk.html#activatefeature)
* Adding URI encoding for [`CustomData`](https://developers.kameleoon.com/php-sdk.html#customdata) & [`PageView`](https://developers.kameleoon.com/php-sdk.html#pageview)
* Added VisitorCodeNotValid exception when exceeding the limit of 255 chars for [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature) ,  [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment) , [`trackConversion`](https://developers.kameleoon.com/php-sdk.html#trackConversion) ,
    [`addData`](https://developers.kameleoon.com/php-sdk.html#addData) , [`flush`](https://developers.kameleoon.com/php-sdk.html#flush)
* Fixed searching by featureKey for [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature)
* Added boolean, number and JSON objects to [`obtainFeatureVariable`](https://developers.kameleoon.com/php-sdk.html#obtainfeaturevariable) as a returned values (before it returns only strings)
* Fixed issue with variationId == `origin` for [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature) and [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment)
* Added checking for status of site (Enable / Disable). Related to [`activateFeature`](https://developers.kameleoon.com/php-sdk.html#activatefeature) and [`triggerExperiment`](https://developers.kameleoon.com/php-sdk.html#triggerexperiment)

# Deprecated versions
All of the versions listed below are no longer supported and we strongly advise to upgrade to the latest version.

## 2.0.7
* Performance improvements

## 2.0.6
* Retrieve experiments with status used_as_personalization
* Update job to handle json

## 2.0.5
* Add DEVIATED status for experiment / feature flags
* Add Kameleoon-client custom header

## 2.0.4
* Update priority for creating cookie, domain parameter is taken if set in configuration

## 2.0.3
* Change configuration file (.conf) to JSON file
* Add options about cookies (samesite, secure, httponly, domain)
* Set domain as optional for obtainVisitorCode as can be declared on configuration
* Update job to log errors

## 2.0.2
* Add security when parsing json

## 2.0.1
* Add security when fetching empty response.

## 2.0.0
* Fetch configurations from automation api.
* Add Feature flags.
* Add targeting for experiments / feature flags.
* Add obtainVariationAssociatedData.
* Change Kameleoon\Exceptions to Kameleoon\Exception
* Rename Custom into CustomData
* Update default configuration path
* Update job name

## 1.1.9
* Update configuration file naming to be able to load multiple site code.
