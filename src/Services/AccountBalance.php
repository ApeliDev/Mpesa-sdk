<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * AccountBalance Service
 * 
 * Handles M-Pesa Account Balance API requests with full validation and logging
 */
class AccountBalance
{
    /**
     * @var MpesaConfig Configuration instance
     */
    protected $config;

    /**
     * @var MpesaClient HTTP client instance
     */
    protected $client;

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
    }

    /**
     * Fetch account balance
     * 
     * @param array $parameters {
     *     @var string $Initiator            The name of the initiator
     *     @var string $SecurityCredential   Encrypted credential
     *     @var string $CommandID            Command ID (AccountBalance)
     *     @var string $PartyA               Organization shortcode
     *     @var string $IdentifierType       Type of identifier (1=MSISDN, 2=Till, 4=Shortcode)
     *     @var string $Remarks              Comments
     *     @var string $QueueTimeOutURL      Result URL
     *     @var string $ResultURL            Timeout URL
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function fetch(array $parameters): array
    {
        $this->validateParameters($parameters);

        $payload = $this->buildPayload($parameters);

        $endpoint = $this->config->isProduction() 
            ? $this->config->get('MPESA_ACCOUNT_BALANCE_URL_PRODUCTION')
            : $this->config->get('MPESA_ACCOUNT_BALANCE_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate request parameters
     * 
     * @param array $parameters
     * @throws InvalidRequestException
     */
    protected function validateParameters(array $parameters): void
    {
        $required = [
            'Initiator',
            'SecurityCredential',
            'CommandID',
            'PartyA',
            'IdentifierType',
            'Remarks',
            'QueueTimeOutURL',
            'ResultURL'
        ];

        foreach ($required as $field) {
            if (empty($parameters[$field])) {
                throw InvalidRequestException::missingParameter($field);
            }
        }

        if (!in_array($parameters['IdentifierType'], [1, 2, 4])) {
            throw InvalidRequestException::invalidValue(
                'IdentifierType', 
                $parameters['IdentifierType'],
                [1, 2, 4]
            );
        }

        if (empty($this->config->get('MPESA_INITIATOR_NAME'))) {
            throw new InvalidRequestException(
                'Initiator name not configured in environment',
                500
            );
        }
    }

    /**
     * Build the complete API payload
     * 
     * @param array $parameters
     * @return array
     */
    protected function buildPayload(array $parameters): array
    {
        return [
            'Initiator' => $parameters['Initiator'] ?? $this->config->get('MPESA_INITIATOR_NAME'),
            'SecurityCredential' => $parameters['SecurityCredential'],
            'CommandID' => $parameters['CommandID'] ?? 'AccountBalance',
            'PartyA' => $parameters['PartyA'] ?? $this->config->get('MPESA_SHORTCODE'),
            'IdentifierType' => $parameters['IdentifierType'],
            'Remarks' => $parameters['Remarks'] ?? 'Checking account balance',
            'QueueTimeOutURL' => $parameters['QueueTimeOutURL'],
            'ResultURL' => $parameters['ResultURL'],
            'Occasion' => $parameters['Occasion'] ?? '',
            'Timestamp' => $this->generateTimestamp()
        ];
    }

    /**
     * Generate current timestamp in required format
     * 
     * @return string
     */
    protected function generateTimestamp(): string
    {
        return (new DateTime())->format('YmdHis');
    }

    /**
     * Get the default account balance callback URLs from config
     * 
     * @return array
     */
    public function getDefaultCallbackUrls(): array
    {
        return [
            'ResultURL' => $this->config->get('MPESA_ACCOUNT_BALANCE_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_ACCOUNT_BALANCE_TIMEOUT_URL')
        ];
    }
}