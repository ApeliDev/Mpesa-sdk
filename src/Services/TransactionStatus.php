<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * TransactionStatus Service
 * 
 * Handles status queries for all M-Pesa transaction types including:
 * - STK Push transactions
 * - C2B payments
 * - B2C payments
 * - B2B payments
 * - Reversal transactions
 */
class TransactionStatus
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
     * Supported identifier types
     */
    const IDENTIFIER_TYPES = [
        'MSISDN' => 1,
        'TILL_NUMBER' => 2,
        'SHORTCODE' => 4
    ];

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
     * Query transaction status
     * 
     * @param array $queryDetails {
     *     @var string $TransactionID      Original transaction ID
     *     @var string $Initiator         Initiator name
     *     @var string $PartyA            Organization shortcode
     *     @var int    $IdentifierType    Identifier type (1=MSISDN, 2=Till, 4=Shortcode)
     *     @var string $Remarks           Query remarks
     *     @var string $Occasion          Optional occasion
     *     @var string $QueueTimeOutURL   Callback timeout URL
     *     @var string $ResultURL         Callback result URL
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function queryStatus(array $queryDetails): array
    {
        $this->validateQueryDetails($queryDetails);

        $payload = $this->buildQueryPayload($queryDetails);

        $endpoint = $this->config->isProduction()
            ? $this->config->get('MPESA_TRANSACTION_STATUS_URL_PRODUCTION')
            : $this->config->get('MPESA_TRANSACTION_STATUS_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate query details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validateQueryDetails(array $details): void
    {
        $required = [
            'TransactionID',
            'Initiator',
            'PartyA',
            'IdentifierType',
            'Remarks',
            'QueueTimeOutURL',
            'ResultURL'
        ];

        foreach ($required as $field) {
            if (empty($details[$field])) {
                throw InvalidRequestException::missingParameter($field);
            }
        }

        $validIdentifierTypes = array_values(self::IDENTIFIER_TYPES);
        if (!in_array($details['IdentifierType'], $validIdentifierTypes)) {
            throw InvalidRequestException::invalidValue(
                'IdentifierType',
                $details['IdentifierType'],
                $validIdentifierTypes
            );
        }
    }

    /**
     * Build complete query payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildQueryPayload(array $details): array
    {
        return [
            'CommandID' => 'TransactionStatusQuery',
            'Initiator' => $details['Initiator'] ?? $this->config->get('MPESA_INITIATOR_NAME'),
            'SecurityCredential' => $this->generateSecurityCredential(),
            'TransactionID' => $details['TransactionID'],
            'PartyA' => $details['PartyA'] ?? $this->config->get('MPESA_SHORTCODE'),
            'IdentifierType' => $details['IdentifierType'],
            'Remarks' => $details['Remarks'] ?? 'Transaction status query',
            'QueueTimeOutURL' => $details['QueueTimeOutURL'],
            'ResultURL' => $details['ResultURL'],
            'Occasion' => $details['Occasion'] ?? '',
            'Timestamp' => $this->generateTimestamp()
        ];
    }

    /**
     * Generate security credential
     * 
     * @return string
     * @throws MpesaException If credential generation fails
     */
    protected function generateSecurityCredential(): string
    {
        $initiatorPassword = $this->config->get('MPESA_INITIATOR_PASSWORD');
        $certificatePath = $this->config->get('MPESA_CERTIFICATE_PATH');

        if (empty($initiatorPassword) || empty($certificatePath)) {
            throw new MpesaException('Security credential configuration incomplete');
        }

        // Implement your certificate-based encryption here
        return base64_encode(openssl_encrypt(
            $initiatorPassword,
            'AES-256-CBC',
            file_get_contents($certificatePath),
            OPENSSL_RAW_DATA,
            str_repeat('0', 16)
        ));
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
     * Get supported identifier types
     * 
     * @return array
     */
    public function getSupportedIdentifierTypes(): array
    {
        return self::IDENTIFIER_TYPES;
    }

    /**
     * Get default callback URLs from config
     * 
     * @return array
     */
    public function getDefaultCallbackUrls(): array
    {
        return [
            'ResultURL' => $this->config->get('MPESA_TRANSACTION_STATUS_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_TRANSACTION_STATUS_TIMEOUT_URL')
        ];
    }

    /**
     * Quick status check with minimal parameters
     * 
     * @param string $transactionID
     * @param string $identifierType
     * @return array
     * @throws InvalidRequestException
     * @throws MpesaException
     */
    public function quickCheck(string $transactionID, string $identifierType = '4'): array
    {
        $defaultUrls = $this->getDefaultCallbackUrls();

        return $this->queryStatus([
            'TransactionID' => $transactionID,
            'Initiator' => $this->config->get('MPESA_INITIATOR_NAME'),
            'PartyA' => $this->config->get('MPESA_SHORTCODE'),
            'IdentifierType' => $identifierType,
            'Remarks' => 'Quick status check',
            'QueueTimeOutURL' => $defaultUrls['QueueTimeOutURL'],
            'ResultURL' => $defaultUrls['ResultURL']
        ]);
    }
}