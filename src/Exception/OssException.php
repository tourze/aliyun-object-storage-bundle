<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Exception;

class OssException extends \Exception
{
    public function __construct(
        string $message,
        private readonly int $httpStatusCode = 0,
        private readonly string $responseBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
