<?php

namespace ApeliMpesa\Mpesa\Http;

use ApeliMpesa\Mpesa\Auth\MpesaAuth;
use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Traits\LoggingTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * MpesaClient Class
 * 
 * Handles HTTP requests to the M-Pesa API
 * 
 * @package ApeliMpesa\Mpesa\Http
 */
class MpesaClient
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
     * @var Client
     */
    protected $httpClient;

    /**
     * Constructor
     * 
     * @param MpesaConfig $config
     * @param MpesaAuth $auth
     */
    public function __construct(MpesaConfig $config, MpesaAuth $auth)
    {
        $this->config = $config;
        $this->auth = $auth;
        $this->initializeLogger();
        $this->initializeHttpClient();
    }

    /**
     * Initialize the HTTP client with default configurations
     * 
     * @return void
     */
    protected function initializeHttpClient(): void
    {
        $this->httpClient = new Client([
            'timeout' => (int) $this->config->get('HTTP_TIMEOUT', 30),
            'connect_timeout' => (int) $this->config->get('HTTP_TIMEOUT', 30),
            'http_errors' => false, 
        ]);
    }

    /**
     * Make a GET request to the M-Pesa API
     * 
     * @param string $url
     * @param array $queryParams
     * @return mixed
     * @throws MpesaException
     */
    public function get(string $url, array $queryParams = [])
    {
        return $this->request('GET', $url, [
            RequestOptions::QUERY => $queryParams
        ]);
    }

    /**
     * Make a POST request to the M-Pesa API
     * 
     * @param string $url
     * @param array $data
     * @return mixed
     * @throws MpesaException
     */
    public function post(string $url, array $data = [])
    {
        return $this->request('POST', $url, [
            RequestOptions::JSON => $data
        ]);
    }

    /**
     * Make an HTTP request to the M-Pesa API
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url
     * @param array $options Request options
     * @param bool $retryOnAuthFailure Whether to retry on authentication failure
     * @return mixed
     * @throws MpesaException
     */
    protected function request(string $method, string $url, array $options = [], bool $retryOnAuthFailure = true)
    {
        try {
            // Get access token for authentication
            $accessToken = $this->auth->getAccessToken();
            
            // Set the Authorization header
            $options['headers'] = array_merge($options['headers'] ?? [], [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
            
            // Log the request (mask sensitive data)
            $logData = $this->sanitizeDataForLogging($options);
            $this->log('info', "Making {$method} request to {$url}", $logData);
            
            // Make the request
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            
            // Parse the response
            $responseData = json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $responseData = $body;
            }
            
            // Log the response
            $this->log('info', "Received response from {$url}", [
                'status_code' => $statusCode,
                'response' => $responseData
            ]);
            
            // Handle authentication errors
            if ($statusCode === 401 && $retryOnAuthFailure) {
                $this->log('warning', 'Authentication failed, refreshing token and retrying');
                $this->auth->refreshToken();
                return $this->request($method, $url, $options, false);
            }
            
            // Handle API errors
            if ($statusCode >= 400) {
                $errorMessage = $this->extractErrorMessage($responseData);
                $this->log('error', "API error: {$errorMessage}", [
                    'status_code' => $statusCode,
                    'response' => $responseData
                ]);
                
                throw new MpesaException("M-Pesa API error: {$errorMessage}", $statusCode);
            }
            
            return $responseData;
            
        } catch (GuzzleException $e) {
            $this->log('error', 'HTTP request failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw new MpesaException('HTTP request failed: ' . $e->getMessage(), 0);
        }
    }

    /**
     * Extract error message from response
     * 
     * @param mixed $response
     * @return string
     */
    protected function extractErrorMessage($response): string
    {
        if (is_object($response)) {
            if (isset($response->errorMessage)) {
                return $response->errorMessage;
            } elseif (isset($response->errorCode)) {
                return "Error code: {$response->errorCode}";
            } elseif (isset($response->message)) {
                return $response->message;
            } elseif (isset($response->ResponseDescription)) {
                return $response->ResponseDescription;
            }
        }
        
        return 'Unknown error';
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeDataForLogging(array $data): array
    {
        $sanitized = $data;
        
        // Mask Authorization header
        if (isset($sanitized['headers']['Authorization'])) {
            $sanitized['headers']['Authorization'] = 'Bearer **********';
        }
        
        // Mask security credentials
        if (isset($sanitized[RequestOptions::JSON]['SecurityCredential'])) {
            $sanitized[RequestOptions::JSON]['SecurityCredential'] = '**********';
        }
        
        // Mask passwords
        if (isset($sanitized[RequestOptions::JSON]['Password'])) {
            $sanitized[RequestOptions::JSON]['Password'] = '**********';
        }
        
        return $sanitized;
    }
}