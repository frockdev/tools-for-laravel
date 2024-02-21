<?php

namespace FrockDev\ToolsForLaravel\Exceptions;

class HttpClientExceptionData
{
    public string $url;
    public array $requestData;
    public string $method;
    public int $responseCode;
    public array $clientHeaders;
    public ?array $responseHeaders;
    public mixed $responseBody;

    public array $context;

    public function getAsArray(): array {
        return [
            'url' => $this->url,
            'requestData' => $this->requestData,
            'method' => $this->method,
            'responseCode' => $this->responseCode,
            'clientHeaders' => $this->clientHeaders,
            'responseHeaders' => $this->responseHeaders,
            'responseBody' => $this->responseBody,
            'context' => $this->context,
        ];
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function setRequestData(array $requestData): self
    {
        $this->requestData = $requestData;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function setResponseCode(int $responseCode): self
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function setClientHeaders(array $clientHeaders): self
    {
        $this->clientHeaders = $clientHeaders;
        return $this;
    }

    public function setResponseHeaders(?array $responseHeaders): self
    {
        $this->responseHeaders = $responseHeaders;
        return $this;
    }

    public function setResponseBody(mixed $responseBody): self
    {
        $this->responseBody = $responseBody;
        return $this;
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }
}
