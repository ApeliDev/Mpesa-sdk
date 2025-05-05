<?php

namespace ApeliMpesa\Mpesa\Auth;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Exceptions\AuthenticationException;
use ApeliMpesa\Mpesa\Traits\LoggingTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * MpesaAuth Class
 * 
 * Handles authentication with the M-Pesa API
 * 
 * @package ApeliMpesa\Mpesa\Auth
 */
class MpesaAuth
{
    use LoggingTrait;

    /**
     * @var MpesaConfig
     */
    protected $config;

    /**
     * @var string|null
     */
    protected $accessToken;

    /**
     * @var int
     */
    protected $expiresAt = 0;

    /**
     * Constructor
     * 
     * @param MpesaConfig $config
     */
    public function __construct(MpesaConfig $config)
    {
        $this->config = $config;
        $this->initializeLogger();
    }

    /**
     * Get the access token
     * 
     * @param bool $forceRefresh Whether to force refresh the token
     * @return string
     * @throws AuthenticationException
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        // Check if we already have a valid token
        if (!$forceRefresh && $this->accessToken && time() < $this->expiresAt) {
            return $this->accessToken;
        }

        return $this->generateAccessToken();
    }

    /**
     * Generate a new access token
     * 
     * @return string
     * @throws AuthenticationException
     */
    protected function generateAccessToken(): string
    {
        $consumerKey = $this->config->get('MPESA_CONSUMER_KEY');
        $consumerSecret = $this->config->get('MPESA_CONSUMER_SECRET');
        
        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new AuthenticationException(
                'Missing M-Pesa API credentials (Consumer Key or Consumer Secret)'
            );
        }

        // Get the appropriate URL based on environment
        $tokenUrl = $this->config->get(
            $this->config->isProduction() ? 'MPESA_OAUTH_TOKEN_URL_PRODUCTION' : 'MPESA_OAUTH_TOKEN_URL_SANDBOX'
        );

        $client = new Client([
            'timeout' => (int) $this->config->get('HTTP_TIMEOUT', 30),
            'connect_timeout' => (int) $this->config->get('HTTP_TIMEOUT', 30)
        ]);

        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        try {
            $this->log('info', 'Requesting M-Pesa access token');
            
            $response = $client->get($tokenUrl, [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                    'Accept' => 'application/json',
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (!isset($body['access_token'])) {
                throw new AuthenticationException('Invalid response from M-Pesa OAuth server');
            }

            $this->accessToken = $body['access_token'];
            
            // Calculate expiry time (usually 3600 seconds / 1 hour)
            $expiresIn = $body['expires_in'] ?? 3600;
            $this->expiresAt = time() + $expiresIn - 60; // Subtract 60 seconds to be safe
            
            $this->log('info', 'M-Pesa access token obtained', [
                'expires_in' => $expiresIn,
                'expires_at' => date('Y-m-d H:i:s', $this->expiresAt)
            ]);

            return $this->accessToken;
            
        } catch (GuzzleException $e) {
            $this->log('error', 'Failed to get M-Pesa access token', [
                'error' => $e->getMessage()
            ]);
            
            throw new AuthenticationException(
                'Failed to authenticate with M-Pesa API: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if the current token is valid
     * 
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->accessToken !== null && time() < $this->expiresAt;
    }

    /**
     * Force token refresh
     * 
     * @return string New access token
     * @throws AuthenticationException
     */
    public function refreshToken(): string
    {
        return $this->generateAccessToken();
    }
}