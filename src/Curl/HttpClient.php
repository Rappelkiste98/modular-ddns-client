<?php

namespace Src\Curl;

use Src\Exception\HttpClientException;
use CurlHandle;

class HttpClient
{
    final const CONTENT_TYPE_FORMDATA = 'multipart/form-data';
    final const CONTENT_TYPE_JSON = 'application/json';
    final const CONTENT_TYPE_WWWFORM = 'application/x-www-form-urlencoded';
    final const METHOD_GET = 'GET';
    final const METHOD_POST = 'POST';
    final const METHOD_PUT = 'PUT';
    final const METHOD_DELETE = 'DELETE';
    private const SUCCESS_CODES = [200, 201, 202];

    private string $url;
    private array $headers = [];
    private array $options = [CURLOPT_TIMEOUT => 90, CURLOPT_RETURNTRANSFER => true];
    private CurlHandle $handle;
    private int $httpCode = 0;
    private bool $disableHttpCodeValidation = false;
    private string $method = self::METHOD_GET;

    public function __construct(string $url, array $options = [])
    {
        $this->url = $url;

        if(count($options) != 0) {
            $this->options = $options;
        }

        $this->handle = curl_init($this->url);
        curl_setopt_array($this->handle, $this->options);
    }

    public static function setUrlParam(string $url, string $param, string $value): string
    {
        $url .= str_contains($url, '?') ? '' : '?';
        $url .= str_contains($url, '=') ? '&' : '';
        $url .= $param . '=' . $value;

        return $url;
    }

    public static function addUrlParams(string $baseUrl, array $params): string
    {
        foreach ($params as $key => $value) {
            $baseUrl = self::setUrlParam($baseUrl, $key, $value);
        }

        return $baseUrl;
    }

    private function setHttpHeader(string $headerKey, string $headerValue): void
    {
        $this->headers[$headerKey] = $headerValue;

        $headerOpts = [];
        foreach ($this->headers as $key => $value) {
            $headerOpts[] = $key . ': ' . $value;
        }
        $this->options[CURLOPT_HTTPHEADER] = $headerOpts;
        curl_setopt_array($this->handle, $this->options);
    }

    private function setCurlOption(int $curlOption, $value): void
    {
        $this->options[$curlOption] = $value;
        curl_setopt_array($this->handle, $this->options);
    }

    private function getCurlOption(int $curlOption): mixed
    {
        return $this->options[$curlOption] ?? null;
    }

    private function setHttpMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getHttpMethod(): string
    {
        return $this->method;
    }

    private function setHttpPost(string $contentType, string|array $data): void
    {
        $this->setHttpHeader('Content-Type', $contentType);
        $this->setCurlOption(CURLOPT_POST, true);
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);
    }

    public function setHttpBearerToken(string $token): void
    {
        $this->setHttpHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * @throws HttpClientException
     */
    private function executeRequest(bool $json = false): string|array
    {
        $response = curl_exec($this->handle);
        $curlError = curl_error($this->handle);
        $this->httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);

        if (!$response || ($json && $response == '' || $curlError !== '')) {
            $message = sprintf('"%s" => %s', $this->getUrl(), ($curlError === '') ? 'No Response Data' : $curlError);
            throw new HttpClientException($message);
        }

        if (!in_array($this->httpCode, $this::SUCCESS_CODES) && !$this->isDisableHttpCodeValidation()) {
            $message = sprintf('"%s" => HttpCode: %d Response: %s', $this->url, $this->getHttpCode(), $response);
            throw new HttpClientException($message);
        }

        $responseContentType = curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);

        curl_close($this->handle);
        LOGGER->request($this);

        $response = ($json || $responseContentType === self::CONTENT_TYPE_JSON) ? json_decode($response, true) : $response;

        return $response ?? ($json ? [] : '');
    }

    /**
     * @throws HttpClientException
     */
    public function getRequest(bool $json = false): string|array
    {
        $this->setHttpMethod(self::METHOD_GET);
        return $this->executeRequest($json);
    }

    /**
     * @throws HttpClientException
     */
    public function postRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = match ($contentType) {
            self::CONTENT_TYPE_JSON => json_encode($data),
            self::CONTENT_TYPE_FORMDATA, self::CONTENT_TYPE_WWWFORM => $data,
        };

        $this->setHttpMethod(self::METHOD_POST);
        $this->setHttpHeader('Content-Type', $contentType);
        $this->setCurlOption(CURLOPT_POST, true);
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        return $this->executeRequest($json);
    }

    /**
     * @throws HttpClientException
     */
    public function putRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = match ($contentType) {
            self::CONTENT_TYPE_JSON => json_encode($data),
            self::CONTENT_TYPE_FORMDATA, self::CONTENT_TYPE_WWWFORM => http_build_query($data),
        };
        $contentLength = strlen($data);

        $this->setHttpMethod(self::METHOD_PUT);
        $this->setHttpHeader('Content-Type', $contentType);
        $this->setHttpHeader('Content-Length', $contentLength);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        return $this->executeRequest($json);
    }

    /**
     * @throws HttpClientException
     */
    public function deleteRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = match ($contentType) {
            self::CONTENT_TYPE_JSON => json_encode($data),
            self::CONTENT_TYPE_FORMDATA, self::CONTENT_TYPE_WWWFORM => http_build_query($data),
        };
        $contentLength = strlen($data);

        $this->setHttpMethod(self::METHOD_DELETE);
        $this->setHttpHeader('Content-Type', $contentType);
        $this->setHttpHeader('Content-Length', $contentLength);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        return $this->executeRequest($json);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isDisableHttpCodeValidation(): bool
    {
        return $this->disableHttpCodeValidation;
    }

    public function setDisableHttpCodeValidation(bool $disableHttpCodeValidation = true): self
    {
        $this->disableHttpCodeValidation = $disableHttpCodeValidation;
        return $this;
    }
}