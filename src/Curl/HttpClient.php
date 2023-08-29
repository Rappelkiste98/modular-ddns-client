<?php

namespace Acme\Curl;

use Acme\Exception\HttpClientException;
use CurlHandle;

class HttpClient
{
    private string $url;
    private array $headers = [];
    private array $options = [CURLOPT_TIMEOUT => 90, CURLOPT_RETURNTRANSFER => true];
    private CurlHandle $handle;
    private int $httpCode = 0;

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
    public function getRequest(bool $json = false): string|array
    {
        $response = curl_exec($this->handle);
        $this->httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        if(!$response || ($json && $response == '')) {
            $message = (curl_error($this->handle) == '') ? 'No Response Data' : curl_error($this->handle);
            throw new HttpClientException($message);
        }

        curl_close($this->handle);

        return $json ? json_decode($response, true) : $response;
    }

    /**
     * @throws HttpClientException
     */
    public function postRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = ($contentType == 'json') ? json_encode($data) : $data;
        $contentType = match ($contentType) {
            'json' => 'application/json',
            'form-data' => 'multipart/form-data',
            default => 'application/x-www-form-urlencoded',
        };

        $this->setHttpHeader('Content-Type', $contentType);
        $this->setCurlOption(CURLOPT_POST, true);
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($this->handle);
        $this->httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        if(!$response || ($json && $response == '')) {
            $message = (curl_error($this->handle) == '') ? 'No Response Data' : curl_error($this->handle);
            throw new HttpClientException($message);
        }

        curl_close($this->handle);

        return $json ? json_decode($response, true) : $response;
    }

    /**
     * @throws HttpClientException
     */
    public function putRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = ($contentType == 'json') ? json_encode($data) : $data;
        $contentLength = strlen(($contentType == 'form-data') ? strlen(http_build_query($data)) : $data);
        $contentType = match ($contentType) {
            'json' => 'application/json',
            'form-data' => 'multipart/form-data',
            default => 'application/x-www-form-urlencoded',
        };

        $this->setHttpHeader('Content-Type', $contentType);
        $this->setHttpHeader('Content-Length', $contentLength);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($this->handle);
        $this->httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        if(!$response || ($json && $response == '')) {
            $message = (curl_error($this->handle) == '') ? 'No Response Data' : curl_error($this->handle);
            throw new HttpClientException($message);
        }

        curl_close($this->handle);

        return $json ? json_decode($response, true) : $response;
    }

    /**
     * @throws HttpClientException
     */
    public function deleteRequest(string|array $data, string $contentType = '', bool $json = false): string|array
    {
        $data = ($contentType == 'json') ? json_encode($data) : $data;
        $contentLength = strlen(($contentType == 'form-data') ? strlen(http_build_query($data)) : $data);
        $contentType = match ($contentType) {
            'json' => 'application/json',
            'form-data' => 'multipart/form-data',
            default => 'application/x-www-form-urlencoded',
        };

        $this->setHttpHeader('Content-Type', $contentType);
        $this->setHttpHeader('Content-Length', $contentLength);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $this->setCurlOption(CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($this->handle);
        $this->httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        if(!$response || ($json && $response == '')) {
            $message = (curl_error($this->handle) == '') ? 'No Response Data' : curl_error($this->handle);
            throw new HttpClientException($message);
        }

        curl_close($this->handle);

        return $json ? json_decode($response, true) : $response;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}