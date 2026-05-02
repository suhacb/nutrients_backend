<?php

namespace App\Exceptions;

class LlmRequestFailedException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
