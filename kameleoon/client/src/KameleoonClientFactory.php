<?php

declare(strict_types=1);

namespace Kameleoon;

use Kameleoon\Hybrid\HybridManagerImpl;
use Kameleoon\Logging\KameleoonLogger;

class KameleoonClientFactory
{
    private array $clients = [];

    public static function create(
        string $siteCode,
        ?string $configurationFilePath = "/etc/kameleoon/client-php.json"
    ) {
        KameleoonLogger::info("CALL: KameleoonClientFactory->create(siteCode: '%s', configurationPath: '%s')",
            $siteCode, $configurationFilePath);
        $client = null;
        if (!in_array($siteCode, self::getInstance()->clients)) {
            $kameleoonConfig = KameleoonClientConfig::readFromFile($configurationFilePath);
            $client = KameleoonClientFactory::createWithConfig(
                $siteCode,
                $kameleoonConfig
            );
        } else {
            $client = self::getInstance()->clients[$siteCode];
        }
        KameleoonLogger::info("RETURN: KameleoonClientFactory->create(siteCode: '%s', configurationPath: '%s') -> (client)",
            $siteCode, $configurationFilePath);
        return $client;
    }

    public static function createWithConfig(
        string $siteCode,
        KameleoonClientConfig $kameleoonConfig
    ) {
        KameleoonLogger::info("CALL: KameleoonClientFactory->createWithConfig(siteCode: '%s', config: %s)",
            $siteCode, $kameleoonConfig);
        if (!in_array($siteCode, self::getInstance()->clients)) {
            self::getInstance()->clients[$siteCode] = new KameleoonClientImpl($siteCode, $kameleoonConfig);
        }
        $client = self::getInstance()->clients[$siteCode];
        KameleoonLogger::info("RETURN: KameleoonClientFactory->create(siteCode: '%s', config: %s) -> (client)",
            $siteCode, $kameleoonConfig);
        return $client;
    }

    public static function forget($siteCode)
    {
        unset(self::getInstance()->clients[$siteCode]);
    }

    private static $instance = null;

    private static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new KameleoonClientFactory();
        }

        return self::$instance;
    }
}
