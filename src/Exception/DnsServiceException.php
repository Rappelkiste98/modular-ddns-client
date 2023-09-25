<?php

namespace Src\Exception;

use Throwable;

class DnsServiceException extends \Exception
{
    private string|array|null $response;

    public function __construct(string $message = "", string|array|null $response = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): string|array|null
    {
        return $this->response;
    }
}