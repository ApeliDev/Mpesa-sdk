<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * ReverseTransaction Service
 * 
 * Handles reversal of all M-Pesa transaction types including:
 * - STK Push transactions
 * - C2B payments
 * - B2C payments
 * - B2B payments
 */
class ReverseTransaction
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
     * Supported identifier types for reversal
     */
    const IDENTIFIER_TYPES = [
        'MSISDN' => 1,
        'TILL_NUMBER' => 2,
        'SHORTCODE' => 4,
        'CUSTOMER_NAME' => 5
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
     * Reverse a transaction
     * 
     * @param array $reversalDetails {
     *     @var string $TransactionID      Original transaction ID
     *     @var float $Amount             Transaction amount
     *     @var string $ReceiverParty     Receiver shortcode/phone
     *     @var string $Initiator         Initiator name
     *     @var string $ReceiverType      Receiver identifier type (1-4)
     *     @var string $Remarks           Reversal reason
     *     @var string $Occasion          Optional occasion
     *     @var string $QueueTimeOutURL   Callback timeout URL
     *     @var string $ResultURL         Callback result URL
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function reverse(array $reversalDetails): array
    {
        $this->validateReversalDetails($reversalDetails);

        $payload = $this->buildReversalPayload($reversalDetails);

        $endpoint = $this->config->isProduction()
            ? $this->config->get('MPESA_REVERSAL_URL_PRODUCTION')
            : $this->config->get('MPESA_REVERSAL_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate reversal details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validateReversalDetails(array $details): void
    {
        $required = [
            'TransactionID',
            'Amount',
            'ReceiverParty',
            'Initiator',
            'ReceiverType',
            'Remarks',
            'QueueTimeOutURL',
            'ResultURL'
        ];

        foreach ($required as $field) {
            if (empty($details[$field])) {
                throw InvalidRequestException::missingParameter($field);
            }
        }

        if (!is_numeric($details['Amount']) || $details['Amount'] <= 0) {
            throw InvalidRequestException::invalidValue('Amount', $details['Amount'], ['positive number']);
        }

        $validIdentifierTypes = array_values(self::IDENTIFIER_TYPES);
        if (!in_array($details['ReceiverType'], $validIdentifierTypes)) {
            throw InvalidRequestException::invalidValue(
                'ReceiverType',
                $details['ReceiverType'],
                $validIdentifierTypes
            );
        }
    }

    /**
     * Build complete reversal payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildReversalPayload(array $details): array
    {
        return [
            'CommandID' => 'TransactionReversal',
            'Initiator' => $details['Initiator'] ?? $this->config->get('MPESA_INITIATOR_NAME'),
            'SecurityCredential' => $this->generateSecurityCredential(),
            'TransactionID' => $details['TransactionID'],
            'Amount' => $details['Amount'],
            'ReceiverParty' => $details['ReceiverParty'],
            'RecieverIdentifierType' => $details['ReceiverType'],
            'Remarks' => $details['Remarks'] ?? 'Transaction reversal',
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
            'ResultURL' => $this->config->get('MPESA_REVERSAL_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_REVERSAL_TIMEOUT_URL')
        ];
    }
}