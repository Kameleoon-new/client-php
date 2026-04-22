<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Exception;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Network\NetProvider;
use Kameleoon\Network\Response;
use Kameleoon\Network\ResponseContentType;

class NetProviderImpl implements NetProvider
{
    const ASYNC_REQUEST_BODY_SIZE_LIMIT = 2560 * 1024; // 2.5 * 1024^2 bytes

    private string $siteCode;
    private string $kameleoonWorkDir;
    private int $asyncRequestBodySizeLimit;

    public function __construct(
        string $siteCode,
        string $kameleoonWorkDir,
        int $asyncRequestBodySizeLimit = self::ASYNC_REQUEST_BODY_SIZE_LIMIT
    ) {
        $this->siteCode = $siteCode;
        $this->kameleoonWorkDir = $kameleoonWorkDir;
        $this->asyncRequestBodySizeLimit = $asyncRequestBodySizeLimit;
    }

    private static function getContent($body, int $responseContentType)
    {
        switch ($responseContentType) {
            case ResponseContentType::TEXT:
                return $body;
            case ResponseContentType::JSON:
                return json_decode($body);
            default:
                return null;
        }
    }

    public function callSync(SyncRequest $request, bool $readHeaders = false): Response
    {
        $ch = curl_init($request->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->httpMethod);
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $request->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if (!empty($request->headers)) {
            $headers = [];
            foreach ($request->headers as $headerName => $headerValue) {
                $headers[] = sprintf("%s: %s", $headerName, $headerValue);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!is_null($request->body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        }
        $responseHeaders = [];
        if ($readHeaders) {
            curl_setopt(
                $ch,
                CURLOPT_HEADERFUNCTION,
                function ($ch, $headerLine) use (&$responseHeaders) {
                    $length = strlen($headerLine);
                    $headerParts = explode(":", $headerLine, 2);
                    if (count($headerParts) != 2) {
                        return $length;
                    }
                    $hname = strtolower(trim($headerParts[0]));
                    $hvalue = trim($headerParts[1]);
                    $responseHeaders[$hname] = $hvalue;
                    return $length;
                }
            );
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        if (empty($err)) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            try {
                $content = self::getContent($body, $request->responseContentType);
                $response = new Response(null, $code, $content, $responseHeaders);
            } catch (Exception $ex) {
                $response = new Response($ex, $code, null, $responseHeaders);
            }
        } else {
            $response = new Response($err, null, null, []);
        }
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
        return $response;
    }

    public function callAsync(AsyncRequest $request): string
    {
        $failureFile = null;
        $hasBody = $request->body !== null;
        $pathBase = $this->selectRequestFilePathBase();
        $requestPath = $pathBase . ".sh";
        $bodyPath = $pathBase . ".dat";
        // Generate request
        $requestText = "curl -s -S -L --tlsv1.2 --tls-max 1.2 -X POST";
        if ($request->headers !== null) {
            foreach ($request->headers as $headerName => $headerValue) {
                $requestText .= sprintf(' -H "%s: %s"', $headerName, $headerValue);
            }
        }
        $requestText .= sprintf(' "%s"', $request->url);
        if ($hasBody) {
            $requestText .= sprintf(' --data-binary "@%s"', $bodyPath);
        }
        $requestText .= PHP_EOL;
        // Write to files
        if (!file_exists($requestPath) && (file_put_contents($requestPath, $requestText, LOCK_EX) === false)) {
            $failureFile = $requestPath;
        } else if ($hasBody && (file_put_contents($bodyPath, $request->body, FILE_APPEND | LOCK_EX) === false)) {
            $failureFile = $bodyPath;
        }
        if ($failureFile !== null) {
            KameleoonLogger::error(
                "Failed to write asynchronous request '%s' to file '%s'",
                $request->url,
                $failureFile,
            );
        }
        return $pathBase;
    }

    private function selectRequestFilePathBase(): string
    {
        $index = 0;
        $pathBase = $this->getRequestFilePathBase();
        $fileCount = 0;
        while (file_exists("$pathBase-$fileCount.dat")) {
            $fileCount++;
        }
        if ($fileCount > 0) {
            $index = $fileCount - 1;
            $latestFile = "$pathBase-$index.dat";
            $latestFileSize = filesize($latestFile);
            if (($latestFileSize === false) || ($latestFileSize >= $this->asyncRequestBodySizeLimit)) {
                $index++;
            }
        }
        return "$pathBase-$index";
    }

    public function getRequestFilePathBase(): string
    {
        return sprintf("%srequests-%d-%s", $this->kameleoonWorkDir, floor(time() / 60), $this->siteCode);
    }
}
