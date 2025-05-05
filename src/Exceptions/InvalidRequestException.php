<?php

namespace ApeliMpesa\Mpesa\Exceptions;

use Exception;
use Throwable;

/**
 * InvalidRequestException Class
 * 
 * Thrown when invalid parameters are provided for an M-Pesa API request
 */
class InvalidRequestException extends Exception
{
    /**
     * @var array Additional context about the error
     */
    protected $context;

    /**
     * Constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $context Additional error context
     */
    public function __construct(
        string $message = "",
        int $code = 400,
        Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional error context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create an instance for missing required parameters
     *
     * @param string $parameterName
     * @return static
     */
    public static function missingParameter(string $parameterName): self
    {
        return new static(
            "Missing required parameter: {$parameterName}",
            400,
            null,
            ['missing_parameter' => $parameterName]
        );
    }

    /**
     * Create an instance for invalid parameter values
     *
     * @param string $parameterName
     * @param mixed $invalidValue
     * @param array $allowedValues
     * @return static
     */
    public static function invalidValue(string $parameterName, $invalidValue, array $allowedValues = []): self
    {
        return new static(
            "Invalid value for parameter '{$parameterName}'",
            400,
            null,
            [
                'parameter' => $parameterName,
                'invalid_value' => $invalidValue,
                'allowed_values' => $allowedValues
            ]
        );
    }
}