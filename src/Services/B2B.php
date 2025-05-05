<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * B2B Service
 * 
 * Handles Business to Business (B2B) transactions with full validation,
 * security credential generation, and response handling.
 */
class B2B
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
     * Supported B2B command IDs
     */
    const COMMAND_IDS = [
        'BusinessPayBill' => 'BusinessPayBill',
        'BusinessBuyGoods' => 'BusinessBuyGoods',
        'DisburseFundsToBusiness' => 'DisburseFundsToBusiness',
        'BusinessToBusinessTransfer' => 'BusinessToBusinessTransfer',
        'MerchantToMerchantTransfer' => 'MerchantToMerchantTransfer'
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
     * Perform a B2B transaction
     * 
     * @param array $transactionDetails {
     *     @var string $Initiator              Name of initiator
     *     @var string $CommandID              B2B command type
     *     @var string $SenderIdentifierType   Sender identifier type (1-4)
     *     @var string $RecieverIdentifierType Receiver identifier type (1-4)
     *     @var float  $Amount                 Transaction amount
     *     @var string $PartyA                 Sender shortcode
     *     @var string $PartyB                 Receiver shortcode
     *     @var string $Remarks                Transaction remarks
     *     @var string $AccountReference       Account reference
     *     @var string $QueueTimeOutURL        Callback timeout URL
     *     @var string $ResultURL              Callback result URL
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function transact(array $transactionDetails): array
    {
        $this->validateTransactionDetails($transactionDetails);

        $payload = $this->buildPayload($transactionDetails);

        $endpoint = $this->config->isProduction() 
            ? $this->config->get('MPESA_B2B_URL_PRODUCTION')
            : $this->config->get('MPESA_B2B_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate transaction details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validateTransactionDetails(array $details): void
    {
        $required = [
            'Initiator',
            'CommandID',
            'SenderIdentifierType',
            'RecieverIdentifierType',
            'Amount',
            'PartyA',
            'PartyB',
            'Remarks',
            'AccountReference',
            'QueueTimeOutURL',
            'ResultURL'
        ];

        foreach ($required as $field) {
            if (empty($details[$field])) {
                throw InvalidRequestException::missingParameter($field);
            }
        }

        if (!in_array($details['CommandID'], array_values(self::COMMAND_IDS))) {
            throw InvalidRequestException::invalidValue(
                'CommandID',
                $details['CommandID'],
                array_values(self::COMMAND_IDS)
            );
        }

        if (!is_numeric($details['Amount']) || $details['Amount'] <= 0) {
            throw InvalidRequestException::invalidValue('Amount', $details['Amount'], ['positive number']);
        }

        $validIdentifierTypes = [1, 2, 4];
        if (!in_array($details['SenderIdentifierType'], $validIdentifierTypes)) {
            throw InvalidRequestException::invalidValue(
                'SenderIdentifierType',
                $details['SenderIdentifierType'],
                $validIdentifierTypes
            );
        }

        if (!in_array($details['RecieverIdentifierType'], $validIdentifierTypes)) {
            throw InvalidRequestException::invalidValue(
                'RecieverIdentifierType',
                $details['RecieverIdentifierType'],
                $validIdentifierTypes
            );
        }
    }

    /**
     * Build complete API payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildPayload(array $details): array
    {
        return [
            'Initiator' => $details['Initiator'] ?? $this->config->get('MPESA_INITIATOR_NAME'),
            'SecurityCredential' => $this->generateSecurityCredential(),
            'CommandID' => $details['CommandID'],
            'SenderIdentifierType' => $details['SenderIdentifierType'],
            'RecieverIdentifierType' => $details['RecieverIdentifierType'],
            'Amount' => $details['Amount'],
            'PartyA' => $details['PartyA'] ?? $this->config->get('MPESA_SHORTCODE'),
            'PartyB' => $details['PartyB'],
            'Remarks' => $details['Remarks'] ?? 'B2B Transaction',
            'AccountReference' => $details['AccountReference'],
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

        if (empty($initiatorPassword) || strlen($initiatorPassword) < 8) {
            throw new MpesaException('Invalid initiator password: must be at least 8 characters long.');
        }

        if (!preg_match('/^[a-zA-Z0-9@#$%^&+=]+$/', $initiatorPassword)) {
            throw new MpesaException('Invalid initiator password: contains unsupported characters.');
        }
        $certificatePath = $this->config->get('MPESA_CERTIFICATE_PATH');

        if (empty($initiatorPassword) || empty($certificatePath)) {
            throw new MpesaException('Security credential configuration incomplete');
        }

        if (!file_exists($certificatePath)) {
            throw new MpesaException('Certificate file not found at: ' . $certificatePath);
        }

        $publicKey = file_get_contents($certificatePath);
        if ($publicKey === false) {
            throw new MpesaException('Failed to read the certificate file');
        }

        $encrypted = null;
        if (!openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            $error = openssl_error_string();
            throw new MpesaException('Encryption failed: ' . ($error ? $error : 'Unknown OpenSSL error'));
        }

        return base64_encode($encrypted);
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
     * Get supported B2B command IDs
     * 
     * @return array
     */
    public function getSupportedCommandIds(): array
    {
        return self::COMMAND_IDS;
    }

    /**
     * Get default callback URLs from config
     * 
     * @return array
     */
    public function getDefaultCallbackUrls(): array
    {
        return [
            'ResultURL' => $this->config->get('MPESA_B2B_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_B2B_TIMEOUT_URL')
        ];
    }
}