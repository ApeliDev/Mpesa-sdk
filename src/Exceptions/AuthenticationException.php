<?php

namespace ApeliMpesa\Exceptions;

use Exception;
use Throwable;

/**
 * AuthenticationException Class
 * 
 * Custom exception for authentication-related errors in the M-Pesa API
 * 
 * @package ApeliMpesa\Exceptions
 */
class AuthenticationException extends Exception
{
    /**
     * @var array Additional error context
     */
    protected $context = [];

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
        int $code = 0,
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
     * Create an instance for missing credentials
     *
     * @return static
     */
    public static function missingCredentials(): self
    {
        return new static('Missing authentication credentials', 401);
    }

    /**
     * Create an instance for invalid credentials
     *
     * @return static
     */
    public static function invalidCredentials(): self
    {
        return new static('Invalid authentication credentials', 403);
    }

    /**
     * Create an instance for expired token
     *
     * @return static
     */
    public static function tokenExpired(): self
    {
        return new static('Authentication token has expired', 401);
    }
}