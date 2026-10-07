<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Kameleoon\Events\EventManager;
use Kameleoon\KameleoonClientConfig;
use Kameleoon\Network\AccessToken\AccessTokenSourceFactory;

class NetworkManagerFactoryImpl implements NetworkManagerFactory
{
    public function create(
        string $siteCode,
        KameleoonClientConfig $clientConfig,
        string $kameleoonWorkDir,
        AccessTokenSourceFactory $accessTokenSourceFactory,
        EventManager $eventManager
    ): NetworkManager {
        $urlProvider = new UrlProvider($siteCode, $clientConfig->getNetworkDomain());
        $netProvider = new NetProviderImpl(
            $siteCode,
            $kameleoonWorkDir,
            $clientConfig->getRequestBodySizeLimitBytes(),
            $clientConfig->getProxy()
        );
        return new NetworkManagerImpl(
            $urlProvider,
            $clientConfig->getEnvironment(),
            $clientConfig->getDefaultTimeoutMillisecond(),
            $netProvider,
            $accessTokenSourceFactory,
            $eventManager
        );
    }
}
