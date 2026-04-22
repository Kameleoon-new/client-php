<?php

declare(strict_types=1);

namespace Kameleoon\Network;

interface NetProvider
{
    public function callSync(SyncRequest $request, bool $readHeaders = false): Response;

    // There is no async request in PHP SDK. This type of requests will be be performed with background daemon.
    public function callAsync(AsyncRequest $request): string;

    public function getRequestFilePathBase(): string;
}

final class ResponseContentType
{
    public const NONE = 0;
    public const TEXT = 1;
    public const JSON = 2;
}

class Response
{
    public $error;
    public ?int $code;
    public $body;
    public array $headers;

    public function __construct($error, ?int $code, $body, ?array $headers = null)
    {
        $this->error = $error;
        $this->code = $code;
        $this->body = $body;
        $this->headers = $headers ?? [];
    }

    public function isExpectedStatusCode(): bool
    {
        return ($this->code !== null)
            && ((intdiv($this->code, 100) === 2) || ($this->code === 403) || ($this->code === 304));
    }

    public function __toString(): string
    {
        return "Response{" .
            "Code:" . ($this->code ?? 'null') .
            ",Error:" . ($this->error ?? 'null') .
            ",Body:" . json_encode($this->body) .
            "}";
    }
}

abstract class Request
{
    const GET = "GET";
    const POST = "POST";

    public string $httpMethod;
    public string $url;
    public ?array $headers;
    public ?string $body;
    public bool $isJwtRequired;

    public function __construct(
        string  $httpMethod,
        string  $url,
        ?array  $headers,
        bool    $isJwtRequired = false,
        ?string $body = null
    )
    {
        $this->httpMethod = $httpMethod;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        $this->isJwtRequired = $isJwtRequired;
    }

    public function __toString(): string
    {
        $headers = '';
        if ($this->headers !== null) {
            foreach ($this->headers as $header) {
                $headers .= $header . ",";
            }
        }
        $body = 'null';
        if ($this->body !== null) {
            $body = $this->body;
        }

        $parts = [];

        foreach (($this->headers ?? []) as $name => $value) {
            if ($name === 'Authorization') {
                $value = '***';
            }

            $parts[] = $name . ':' . $value;
        }
        return "Request{" .
            "Method:'" . $this->httpMethod .
            "',Url:'" . $this->url .
            "',Headers:{" . implode(',', $parts) .
            "},Body:'" . $body .
            "'}";
    }
}

class SyncRequest extends Request
{
    public ?int $timeout;
    public int $responseContentType;

    public function __construct(
        string  $httpMethod,
        string  $url,
        ?array  $headers,
        ?int    $timeout,
        int     $responseContentType,
        bool    $isJwtRequired = false,
        ?string $body = null
    )
    {
        parent::__construct($httpMethod, $url, $headers, $isJwtRequired, $body);
        $this->timeout = $timeout;
        $this->responseContentType = $responseContentType;
    }
}

// There is no async request in PHP SDK. This type of requests will be be performed with background daemon.
class AsyncRequest extends Request
{
    public function __construct(string $url, ?array $headers, string $body, bool $isJwtRequired = false)
    {
        parent::__construct(Request::POST, $url, $headers, $isJwtRequired, $body);
    }
}
