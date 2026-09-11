<?php

declare(strict_types=1);

namespace Kameleoon\Network\AccessToken;

use Kameleoon\Helpers\StringHelper;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Network\NetworkManager;

/**
 * The token and the silence record are shared between PHP processes through a file, which holds either a token
 * or a silence record, never both. A script keeps what it loaded for its whole lifetime: it is far shorter than
 * the expiration gap and the silence period, so the loaded state cannot become stale while it runs.
 */
class AccessTokenSourceImpl implements AccessTokenSource
{
    // A token is considered expired this long before its real expiration so that the requests scheduled
    // by the cron job still have time to be sent with a valid token.
    const TOKEN_EXPIRATION_GAP = 300; // in seconds
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
    private string $basicAuthToken;

    // In-memory copy of the file state: a valid token, or the silence flag
    private ?string $cachedToken = null;
    private bool $silent = false;

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
        $this->basicAuthToken = self::constructBasicToken($clientId, $clientSecret);
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
        $token = $this->cachedToken;
        if (($token === null) && !$this->silent) {
            // Cheap unlocked read first: another process may have renewed the token or entered silence already.
            if (file_exists($this->accessTokenFilePath)) {
                $this->loadState(file_get_contents($this->accessTokenFilePath, true));
                $token = $this->cachedToken;
            }
            if (($token === null) && !$this->silent) {
                $token = $this->fetchToken($timeout);
            }
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource->getToken(timeout: %s) -> (token: '%s')",
            $timeout, StringHelper::secret($token));
        return $token;
    }

    public function discardToken(string $token): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->discardToken(token: '%s')", StringHelper::secret($token));
        if ($this->cachedToken === $token) {
            $this->cachedToken = null;
        }
        // Drop it from the shared file too, unless another process has already replaced it.
        if (($fp = fopen($this->accessTokenFilePath, "c+")) !== false) {
            if (flock($fp, LOCK_EX)) {
                $json = json_decode(stream_get_contents($fp));
                if (is_object($json) && (($json->{self::JWT_ACCESS_TOKEN_FIELD} ?? null) === $token)) {
                    ftruncate($fp, 0);
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource->discardToken(token: '%s')", StringHelper::secret($token));
    }

    private function fetchToken(?int $timeout): ?string
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->fetchToken(timeout: %s)", $timeout);
        $token = null;
        // "c+" creates the file if missing without truncating an existing one
        if (($fp = fopen($this->accessTokenFilePath, "c+")) !== false) {
            if (flock($fp, LOCK_EX)) {
                // Re-check under the lock: a process holding it before us may have fetched the token or failed.
                $this->loadState(stream_get_contents($fp));
                $token = $this->cachedToken;
                if (($token === null) && !$this->silent) {
                    $tokenResponse = $this->networkManager->fetchAccessJWToken($this->basicAuthToken, $timeout);
                    if ($tokenResponse === null) {
                        $this->saveSilenceMode($fp, "the request failed");
                    } elseif (!self::isValidTokenResponse($tokenResponse)) {
                        $this->saveSilenceMode($fp, "malformed response: " . json_encode($tokenResponse));
                    } else {
                        $token = $tokenResponse->{self::JWT_ACCESS_TOKEN_FIELD};
                        $this->saveToken($fp, $token, (int)$tokenResponse->{self::JWT_EXPIRES_IN_FIELD});
                    }
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            KameleoonLogger::error("Failed to open access token file '%s'", $this->accessTokenFilePath);
        }
        KameleoonLogger::debug("RETURN: AccessTokenSource->fetchToken(timeout: %s) -> (token: '%s')",
            $timeout, StringHelper::secret($token));
        return $token;
    }

    private static function isValidTokenResponse($tokenResponse): bool
    {
        return is_object($tokenResponse)
            && is_string($tokenResponse->{self::JWT_ACCESS_TOKEN_FIELD} ?? null)
            && is_numeric($tokenResponse->{self::JWT_EXPIRES_IN_FIELD} ?? null);
    }

    /** Replaces the in-memory state with the content of the token file. */
    private function loadState(string $fileContent): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->loadState(fileContent)"); // the content holds the token
        $json = json_decode($fileContent);
        $now = time();
        $token = is_object($json) ? ($json->{self::JWT_ACCESS_TOKEN_FIELD} ?? null) : null;
        $expiresAt = is_object($json) ? (int)($json->{self::JWT_EXPIRES_AT_FIELD} ?? 0) : 0;
        $silentUntil = is_object($json) ? (int)($json->{self::SILENT_AFTER_FETCH_FAILURE_UNTIL_FIELD} ?? 0) : 0;
        $this->cachedToken = (is_string($token) && ($now < $expiresAt)) ? $token : null;
        $this->silent = $now < $silentUntil;
        KameleoonLogger::debug(
            "RETURN: AccessTokenSource->loadState(fileContent) -> (token: '%s', expiresAt: %d, silentUntil: %d)",
            StringHelper::secret($this->cachedToken), $expiresAt, $silentUntil
        );
    }

    private function saveToken($fp, string $token, int $expiresIn): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->saveToken(fp: %s, expiresIn: %d)", $fp, $expiresIn);
        if ($expiresIn <= self::TOKEN_EXPIRATION_GAP) {
            KameleoonLogger::error("Access token life time (%ds) is not long enough to cache the token", $expiresIn);
        }
        $this->cachedToken = $token;
        $this->silent = false;
        self::writeFile($fp, json_encode([
            self::JWT_ACCESS_TOKEN_FIELD => $token,
            self::JWT_EXPIRES_AT_FIELD => time() + $expiresIn - self::TOKEN_EXPIRATION_GAP,
        ]));
        KameleoonLogger::info("Fetched access token");
        KameleoonLogger::debug("RETURN: AccessTokenSource->saveToken(fp: %s, expiresIn: %d)", $fp, $expiresIn);
    }

    private function saveSilenceMode($fp, string $reason): void
    {
        KameleoonLogger::debug("CALL: AccessTokenSource->saveSilenceMode(fp: %s, reason: '%s')", $fp, $reason);
        $this->cachedToken = null;
        $this->silent = true;
        self::writeFile($fp, json_encode([
            self::SILENT_AFTER_FETCH_FAILURE_UNTIL_FIELD => time() + self::SILENCE_PERIOD,
        ]));
        KameleoonLogger::warning("Failed to fetch access token (%s); it will not be requested for %ds",
            $reason, self::SILENCE_PERIOD);
        KameleoonLogger::debug("RETURN: AccessTokenSource->saveSilenceMode(fp: %s, reason: '%s')", $fp, $reason);
    }

    private static function writeFile($fp, string $content): void
    {
        rewind($fp);
        fwrite($fp, $content);
        fflush($fp);
        ftruncate($fp, ftell($fp));
    }
}
