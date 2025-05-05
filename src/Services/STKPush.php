<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Traits\LoggingTrait;
use ApeliMpesa\Mpesa\Helpers\SecurityHelper;

/**
 * STK Push Service
 * 
 * Handles Lipa Na M-Pesa Online (STK Push) functionality
 * This allows customers to make payments via STK push to their phones
 * 
 * @package ApeliMpesa\Mpesa\Services
 */
class STKPush
{
    use LoggingTrait;

    /**
     * @var MpesaConfig
     */
    protected $config;

    /**
     * @var MpesaClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $phoneNumber;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $reference;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $callbackUrl;

    /**
     * @var string
     */
    protected $accountReference;

    /**
     * @var string
     */
    protected $transactionType = 'CustomerPayBillOnline';

    /**
     * Constructor
     * 
     * @param MpesaConfig $config
     * @param MpesaClient $client
     */
    public function __construct(MpesaConfig $config, MpesaClient $client)
    {
        $this->config = $config;
        $this->client = $client;
        $this->callbackUrl = $config->get('MPESA_STK_CALLBACK_URL');
        $this->initializeLogger();
    }

    /**
     * Set the phone number
     * 
     * @param string $phoneNumber Customer phone number (2547XXXXXXXX format)
     * @return $this
     */
    public function setPhoneNumber(string $phoneNumber): self
    {
        // Remove any "+" prefix if exists
        $phoneNumber = ltrim($phoneNumber, '+');
        
        // Validate phone number
        if (!preg_match('/^2547\d{8}$/', $phoneNumber)) {
            throw new MpesaException('Invalid phone number format. Use 2547XXXXXXXX format.');
        }
        
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    /**
     * Set the amount
     * 
     * @param float|int $amount Amount to be paid
     * @return $this
     */
    public function setAmount($amount): self
    {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new MpesaException('Amount must be a positive number.');
        }
        
        $this->amount = (float) $amount;
        return $this;
    }

    /**
     * Set the reference
     * 
     * @param string $reference Transaction reference (e.g., order number)
     * @return $this
     */
    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        $this->accountReference = $reference;
        return $this;
    }

    /**
     * Set the description
     * 
     * @param string $description Transaction description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        if (strlen($description) > 100) {
            $description = substr($description, 0, 97) . '...';
        }
        
        $this->description = $description;
        return $this;
    }

    /**
     * Set custom callback URL
     * 
     * @param string $url Custom callback URL
     * @return $this
     */
    public function setCallbackUrl(string $url): self
    {
        $this->callbackUrl = $url;
        return $this;
    }

    /**
     * Set custom account reference
     * 
     * @param string $accountReference Custom account reference
     * @return $this
     */
    public function setAccountReference(string $accountReference): self
    {
        $this->accountReference = $accountReference;
        return $this;
    }

    /**
     * Set transaction type
     * 
     * @param string $type Transaction type
     * @return $this
     */
    public function setTransactionType(string $type): self
    {
        $this->transactionType = $type;
        return $this;
    }

    /**
     * Generate the password string for STK Push
     * 
     * @return string
     */
    protected function generatePassword(): string
    {
        $shortcode = $this->config->get('MPESA_SHORTCODE');
        $passkey = $this->config->get('MPESA_PASSKEY');
        $timestamp = $this->getTimestamp();
        
        return base64_encode($shortcode . $passkey . $timestamp);
    }

    /**
     * Get the current timestamp in YYYYMMDDHHmmss format
     * 
     * @return string
     */
    protected function getTimestamp(): string
    {
        return date('YmdHis');
    }

    /**
     * Validate required fields before sending request
     * 
     * @throws MpesaException
     */
    protected function validateRequest(): void
    {
        $requiredFields = [
            'phoneNumber' => 'Phone number',
            'amount' => 'Amount',
            'reference' => 'Reference',
            'description' => 'Description',
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($this->$field)) {
                throw new MpesaException("$label is required.");
            }
        }
    }

    /**
     * Prepare request payload
     * 
     * @return array
     */
    protected function prepareRequest(): array
    {
        $timestamp = $this->getTimestamp();
        $shortcode = $this->config->get('MPESA_SHORTCODE');
        
        return [
            'BusinessShortCode' => $shortcode,
            'Password' => $this->generatePassword(),
            'Timestamp' => $timestamp,
            'TransactionType' => $this->transactionType,
            'Amount' => ceil($this->amount),
            'PartyA' => $this->phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $this->phoneNumber,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $this->accountReference,
            'TransactionDesc' => $this->description,
        ];
    }

    /**
     * Send STK Push request
     * 
     * @return object Response object
     * @throws MpesaException
     */
    public function send(): object
    {
        $this->validateRequest();
        $payload = $this->prepareRequest();
        
        $this->log('info', 'Sending STK Push request', [
            'phone' => $this->phoneNumber,
            'amount' => $this->amount,
            'reference' => $this->reference
        ]);
        
        $url = $this->config->get(
            $this->config->isProduction() ? 'MPESA_STK_PUSH_URL_PRODUCTION' : 'MPESA_STK_PUSH_URL_SANDBOX'
        );
        
        try {
            $response = $this->client->post($url, $payload);
            $this->log('info', 'STK Push request successful', [
                'response' => $response
            ]);
            
            return $this->processResponse($response);
        } catch (\Exception $e) {
            $this->log('error', 'STK Push request failed', [
                'error' => $e->getMessage()
            ]);
            
            throw new MpesaException('STK Push request failed: ' . $e->getMessage(), 0);
        }
    }

    /**
     * Process and standardize the response
     * 
     * @param mixed $response
     * @return object
     */
    protected function processResponse($response): object
    {
        if (is_string($response)) {
            $response = json_decode($response);
        } elseif (is_array($response)) {
            $response = (object) $response;
        }
        
        // Add helper methods to the response object
        $responseObject = new \stdClass();
        $responseObject->raw = $response;
        
        // Add success indicator
        $responseObject->isSuccessful = function() use ($response) {
            return isset($response->ResponseCode) && $response->ResponseCode == '0';
        };
        
        // Add getter for CheckoutRequestID
        $responseObject->getCheckoutRequestID = function() use ($response) {
            return $response->CheckoutRequestID ?? null;
        };
        
        // Add getter for error message
        $responseObject->getErrorMessage = function() use ($response) {
            if (isset($response->errorMessage)) {
                return $response->errorMessage;
            } elseif (isset($response->ResponseDescription)) {
                return $response->ResponseDescription;
            } else {
                return 'Unknown error';
            }
        };
        
        return $responseObject;
    }

    /**
     * Query the status of an STK Push transaction
     * 
     * @param string $checkoutRequestId The CheckoutRequestID from the STK Push response
     * @return object Response object
     * @throws MpesaException
     */
    public function query(string $checkoutRequestId): object
    {
        $shortcode = $this->config->get('MPESA_SHORTCODE');
        $timestamp = $this->getTimestamp();
        
        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $this->generatePassword(),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];
        
        $url = $this->config->get(
            $this->config->isProduction() ? 'MPESA_STK_QUERY_URL_PRODUCTION' : 'MPESA_STK_QUERY_URL_SANDBOX'
        );
        
        try {
            $response = $this->client->post($url, $payload);
            return $this->processQueryResponse($response);
        } catch (\Exception $e) {
            throw new MpesaException('STK Push query failed: ' . $e->getMessage(), 0);
        }
    }

    /**
     * Process and standardize the query response
     * 
     * @param mixed $response
     * @return object
     */
    protected function processQueryResponse($response): object
    {
        if (is_string($response)) {
            $response = json_decode($response);
        } elseif (is_array($response)) {
            $response = (object) $response;
        }
        
        $responseObject = new \stdClass();
        $responseObject->raw = $response;
        
        $responseObject->isSuccessful = function() use ($response) {
            return isset($response->ResultCode) && $response->ResultCode == '0';
        };
        
        $responseObject->isCompleted = function() use ($response) {
            return isset($response->ResultCode) && $response->ResultCode == '0';
        };
        
        $responseObject->isPending = function() use ($response) {
            return isset($response->ResponseCode) && $response->ResponseCode == '0' && 
                  (!isset($response->ResultCode) || $response->ResultCode != '0');
        };
        
        $responseObject->getResultDescription = function() use ($response) {
            return $response->ResultDesc ?? null;
        };
        
        return $responseObject;
    }
}