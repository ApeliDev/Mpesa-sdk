<?php

namespace ApeliMpesa\Mpesa\Exceptions;

use Exception;

class MpesaException extends Exception
{
    /**
     * Custom error code for the exception
     * 
     * @var int|null
     */
    protected ?int $errorCode;

    /**
     * MpesaException constructor.
     *
     * @param string $message
     * @param int|null $errorCode
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message, ?int $errorCode = null, int $code = 0, Exception $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the custom error code
     *
     * @return int|null
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * Format the exception as a string
     *
     * @return string
     */
    public function __toString(): string
    {
        $errorCodePart = $this->errorCode ? " [Error Code: {$this->errorCode}]" : '';
        return __CLASS__ . ": {$this->message}{$errorCodePart} in {$this->file} on line {$this->line}";
    }
}