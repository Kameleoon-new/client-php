<?php

declare(strict_types=1);

namespace Kameleoon\Network\AccessToken;

use Kameleoon\Helpers\StringHelper;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Network\NetworkManager;

class AccessTokenSourceImpl implements AccessTokenSource
{
    const TOKEN_EXPIRATION_GAP = 60 * 10; // in seconds
    const SILENCE_PERIOD = 3600; // 1 hour in seconds
    const JWT_ACCESS_TOKEN_FIELD = "access_token";
    const JWT_EXPIRES_IN_FIELD = "expires_in";
    const JWT_EXPIRES_AT_FIELD = "expires_at";
    const SILENT_AFTER_FETCH_FAILURE_UNTIL_FIELD = "silentAfterFetchFailureUntil";
    const ACCESS_TOKEN_FILE = "access_token.json";
    const BASIC_AUTHORIZATION_PREFIX = "Basic ";

    private string $clientId;
    private string $clientSecret;
    private string $accessTokenFilePath;
    private NetworkManager $networkManager;

    private ?string $cachedToken;
    private ?string $basicAuthToken;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $kameleoonWorkDir,
        NetworkManager $networkManager
    ) {
        KameleoonLogger::debug(function () use ($clientId, $clientSecret, $kameleoonWorkDir) {
            return sprintf("CALL: new AccessTokenSourceImpl(clientId: '%s', clientSecret: '%s', kameleoonWorkDir: '%s')",
                StringHelper::secret($clientId), StringHelper::secret($clientSecret), $kameleoonWorkDir);
        });
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accessTokenFilePath = $kameleoonWorkDir . self::ACCESS_TOKEN_FILE;
        $this->networkManager = $networkManager;
        $this->basicAuthToken = $this->constructBasicToken($clientId, $clientSecret);
        KameleoonLogger::debug(function () use ($clientId, $clientSecret, $kameleoonWorkDir) {
            return sprintf("RETURN: new AccessTokenSourceImpl(clientId: '%s', clientSecret: '%s', kameleoonWorkDir: '%s')",
                StringHelper::secret($clientId), StringHelper::secret($clientSecret), $kameleoonWorkDir);
        });
    }

    public static function constructBasicToken(string $clientId, string $clientSecret): string
    {
        $basicTokenContent = $clientId . ':' . $clientSecret;
        return self::BASIC_AUTHORIZATION_PREFIX . base64_encode($basicTokenContent);
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getAccessFileTokenPath(): string
    {
        return $this->accessTokenFilePath;
    }

    public function getNetworkManager(): NetworkManager
    {
        return $this->networkManager;
    }

    public function getToken(?int $timeout = null): ?string
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->getToken(timeout: %s)", $timeout);
        if (isset($this->cachedToken) && $this->cachedToken !== null) {
            return $this->cachedToken;
        }
        if (file_exists($this->accessTokenFilePath)) {
            [$token, ] = self::loadToken(file_get_contents($this->accessTokenFilePath, true));
            if ($token !== null) {
                $this->cachedToken = $token;
                return $token;
            }
        }
        $token = $this->fetchToken($timeout);
        KameleoonLogger::debug("RETURN: AccessTokenSource->getToken(timeout: %s) -> (token: '%s')", $timeout, $token);
        return $token;
    }

    private function fetchToken(?int $timeout = null): ?string
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->fetchToken(timeout: %s)", $timeout);
        if (!file_exists($this->accessTokenFilePath)) {
            $fp = fopen($this->accessTokenFilePath, "a");
            fclose($fp);
        }
        $token = null;
        if (($fp = fopen($this->accessTokenFilePath, "r+")) && flock($fp, LOCK_EX)) {
            [$token, $silentAfterFetchFailure] = self::loadToken(stream_get_contents($fp));
            if (($token === null) && !$silentAfterFetchFailure) {
                $tokenResponse =
                    $this->networkManager->fetchAccessJWToken($this->basicAuthToken, $timeout);
                if ($tokenResponse !== null) {
                    self::saveToken($fp, $tokenResponse);
                    $token = $tokenResponse->{self::JWT_ACCESS_TOKEN_FIELD};
                } else {
                    self::saveSilenceMode($fp);
                }
            }
            $this->cachedToken = $token;
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource->fetchToken(timeout: %s) -> (token: '%s')",
            $timeout, $token);
        return $token;
    }

    private static function loadToken(string $tokenInFile): array
    {
        KameleoonLogger::debug("CALL: AccessTokenSource::loadToken(tokenInFile: '%s')", $tokenInFile);
        $token = null;
        $silentAfterFetchFailure = false;
        $accessTokenJson = json_decode($tokenInFile);
        if ($accessTokenJson !== null) {
            $now = time();
            if ($now < ($accessTokenJson->{self::JWT_EXPIRES_AT_FIELD} ?? 0)) {
                $token = $accessTokenJson->{self::JWT_ACCESS_TOKEN_FIELD};
            } elseif ($now < ($accessTokenJson->{self::SILENT_AFTER_FETCH_FAILURE_UNTIL_FIELD} ?? 0)) {
                $silentAfterFetchFailure = true;
            }
        }
        KameleoonLogger::debug(
            "RETURN: AccessTokenSource::loadToken(tokenInFile: '%s') -> (token: %s, silentAfterFetchFailure: %s)",
            $tokenInFile, $token ?? "null", $silentAfterFetchFailure
        );
        return [$token, $silentAfterFetchFailure];
    }

    private static function saveToken($fp, object $tokenResponse): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource::saveToken(fp: %s, tokenResponse: %s)", $fp,
            $tokenResponse);
        if ($tokenResponse->{SELF::JWT_EXPIRES_IN_FIELD} < self::TOKEN_EXPIRATION_GAP) {
            KameleoonLogger::error("Access token life time (%ss) is not long enough to cache the token",
                    $tokenResponse->{SELF::JWT_EXPIRES_IN_FIELD});
        }
        $tokenResponse->{SELF::JWT_EXPIRES_AT_FIELD} = time() + $tokenResponse->{SELF::JWT_EXPIRES_IN_FIELD};
        unset($tokenResponse->{SELF::JWT_EXPIRES_IN_FIELD});
        if ($jsonEncode = json_encode($tokenResponse)) {
            rewind($fp);
            fwrite($fp, $jsonEncode);
            fflush($fp);
            ftruncate($fp, ftell($fp));
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource::saveToken(fp: %s, tokenResponse: %s)", $fp,
            $tokenResponse);
    }

    private static function saveSilenceMode($fp): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource::saveSilenceMode(fp: %s)", $fp);
        $silentUntil = time() + self::SILENCE_PERIOD;
        $content = '{"' . self::SILENT_AFTER_FETCH_FAILURE_UNTIL_FIELD . "\":$silentUntil}";
        rewind($fp);
        fwrite($fp, $content);
        fflush($fp);
        ftruncate($fp, ftell($fp));
        KameleoonLogger::debug("RETURN: AccessTokenSource::saveSilenceMode(fp: %s)", $fp);
    }

    public function discardToken(string $token): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->discardToken(token: '%s')", $token);
        if (($fp = fopen($this->accessTokenFilePath, "r+")) && flock($fp, LOCK_EX)) {
            [$tokenFile, ] = self::loadToken(stream_get_contents($fp));
            if ($token == $tokenFile) {
                ftruncate($fp, 0);
                $this->cachedToken = null;
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource->discardToken(token: '%s')", $token);
    }
}
