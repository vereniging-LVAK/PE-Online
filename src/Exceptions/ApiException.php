<?php

namespace PeOnline\Exceptions;

/**
 * Exception thrown when API errors occur
 */
class ApiException extends \Exception
{
    private int $apiErrorCode;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->apiErrorCode = $code;
        parent::__construct($message, $code, $previous);
    }

    public function getApiErrorCode(): int
    {
        return $this->apiErrorCode;
    }

}
