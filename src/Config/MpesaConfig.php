<?php

namespace ApeliMpesa\Mpesa\Config;

/**
 * MpesaConfig Class
 * 
 * Handles configuration for the M-Pesa API
 */
class MpesaConfig
{
    protected $configurations = [];

    public function __construct(array $configurations = [])
    {
        $this->configurations = $configurations;
    }

    /**
     * Get a configuration value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->configurations[$key] ?? $default;
    }

    /**
     * Check if the environment is production
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->get('ENVIRONMENT') === 'production';
    }
}