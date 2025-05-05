<?php

namespace ApeliMpesa\Mpesa;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Auth\MpesaAuth;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Services\STKPush;
use ApeliMpesa\Mpesa\Services\C2B;
use ApeliMpesa\Mpesa\Services\B2C;
use ApeliMpesa\Mpesa\Services\B2B;
use ApeliMpesa\Mpesa\Services\AccountBalance; 
use ApeliMpesa\Mpesa\Services\TransactionStatus; 
use ApeliMpesa\Mpesa\Services\ReverseTransaction;
use ApeliMpesa\Mpesa\Exceptions\ConfigurationException;
use ApeliMpesa\Mpesa\Traits\LoggingTrait;

/**
 * Main M-Pesa Class
 * 
 * This is the main entry point for the M-Pesa PHP library.
 * It provides access to all M-Pesa API functionalities.
 * 
 * @package ApeliMpesa\Mpesa
 */
class Mpesa
{
    use LoggingTrait;

    /**
     * @var MpesaConfig
     */
    protected $config;

    /**
     * @var MpesaAuth
     */
    protected $auth;

    /**
     * @var MpesaClient
     */
    protected $client;

    /**
     * Constructor
     * 
     * @param string|null $configPath Path to config file or null to use .env
     * @throws ConfigurationException When configuration is invalid
     */
    public function __construct(string $configPath = null)
    {
        // Initialize configuration
        $configArray = is_string($configPath) ? ['path' => $configPath] : [];
        $this->config = new MpesaConfig($configArray);
        
        // Set up authentication
        $this->auth = new MpesaAuth($this->config);
        
        // Set up HTTP client
        $this->client = new MpesaClient($this->config, $this->auth);
        
        $this->initializeLogger();
        $this->log('info', 'M-Pesa library initialized');
    }

    /**
     * Get STK Push service
     * 
     * @return STKPush
     */
    public function stkPush(): STKPush
    {
        return new STKPush($this->config, $this->client);
    }

    /**
     * Get C2B service
     * 
     * @return C2B
     */
    public function c2b(): C2B
    {
        return new C2B($this->config, $this->client);
    }

    /**
     * Get B2C service
     * 
     * @return B2C
     */
    public function b2c(): B2C
    {
        return new B2C($this->config, $this->client);
    }

    /**
     * Get B2B service
     * 
     * @return B2B
     */
    public function b2b(): B2B
    {
        return new B2B($this->config, $this->client);
    }

    /**
     * Get Account Balance service
     * 
     * @return AccountBalance
     */
    public function accountBalance(): AccountBalance
    {
        return new AccountBalance($this->config, $this->client);
    }

    /**
     * Get Transaction Status service
     * 
     * @return TransactionStatus
     */
    public function transactionStatus(): TransactionStatus
    {
        return new TransactionStatus($this->config, $this->client);
    }

    /**
     * Get Reverse Transaction service
     * 
     * @return ReverseTransaction
     */
    public function reverseTransaction(): ReverseTransaction
    {
        return new ReverseTransaction($this->config, $this->client);
    }

    /**
     * Get authentication instance
     * 
     * @return MpesaAuth
     */
    public function getAuth(): MpesaAuth
    {
        return $this->auth;
    }

    /**
     * Get configuration instance
     * 
     * @return MpesaConfig
     */
    public function getConfig(): MpesaConfig
    {
        return $this->config;
    }

    /**
     * Get HTTP client instance
     * 
     * @return MpesaClient
     */
    public function getClient(): MpesaClient
    {
        return $this->client;
    }
}