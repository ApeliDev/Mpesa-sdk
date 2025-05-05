<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * B2C Service
 * 
 * Handles Business to Customer (B2C) transactions including:
 * - Salary payments
 * - Business payments
 * - Promotion payments
 * - Transaction status queries
 */
class B2C
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
     * Supported B2C command IDs
     */
    const COMMAND_IDS = [
        'SalaryPayment' => 'SalaryPayment',
        'BusinessPayment' => 'BusinessPayment',
        'PromotionPayment' => 'PromotionPayment'
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
     * Initiate a B2C payment
     * 
     * @param array $paymentDetails {
     *     @var string $InitiatorName      Name of initiator
     *     @var string $CommandID          Payment type (SalaryPayment, BusinessPayment, etc.)
     *     @var string $Amount             Payment amount
     *     @var string $PartyA             Organization shortcode
     *     @var string $PartyB             Customer phone number (2547...)
     *     @var string $Remarks            Payment remarks
     *     @var string $Occasion           Payment occasion
     *     @var string $QueueTimeOutURL    Callback timeout URL
     *     @var string $ResultURL          Callback result URL
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function sendPayment(array $paymentDetails): array
    {
        $this->validatePaymentDetails($paymentDetails);

        $payload = $this->buildPaymentPayload($paymentDetails);

        $endpoint = $this->config->isProduction()
            ? $this->config->get('MPESA_B2C_URL_PRODUCTION')
            : $this->config->get('MPESA_B2C_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate payment details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validatePaymentDetails(array $details): void
    {
        $required = [
            'InitiatorName',
            'CommandID',
            'Amount',
            'PartyA',
            'PartyB',
            'Remarks',
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

        if (!preg_match('/^2547\d{8}$/', $details['PartyB'])) {
            throw InvalidRequestException::invalidValue('PartyB', $details['PartyB'], ['2547XXXXXXXX']);
        }
    }

    /**
     * Build complete API payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildPaymentPayload(array $details): array
    {
        return [
            'InitiatorName' => $details['InitiatorName'],
            'SecurityCredential' => $this->generateSecurityCredential(),
            'CommandID' => $details['CommandID'],
            'Amount' => $details['Amount'],
            'PartyA' => $details['PartyA'] ?? $this->config->get('MPESA_SHORTCODE'),
            'PartyB' => $details['PartyB'],
            'Remarks' => $details['Remarks'] ?? 'B2C Payment',
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
        // This is a placeholder - use actual encryption logic
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
     * Get supported B2C command IDs
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
            'ResultURL' => $this->config->get('MPESA_B2C_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_B2C_TIMEOUT_URL')
        ];
    }

    /**
     * Get transaction status
     * 
     * @param string $transactionID
     * @param string $identifierType
     * @param string $initiator
     * @param string $partyA
     * @param string $remarks
     * @return array
     * @throws InvalidRequestException
     * @throws MpesaException
     */
    public function getTransactionStatus(
        string $transactionID,
        string $identifierType = '1',
        string $initiator = null,
        string $partyA = null,
        string $remarks = 'Transaction Status Query'
    ): array {
        $payload = [
            'Initiator' => $initiator ?? $this->config->get('MPESA_INITIATOR_NAME'),
            'SecurityCredential' => $this->generateSecurityCredential(),
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionID,
            'PartyA' => $partyA ?? $this->config->get('MPESA_SHORTCODE'),
            'IdentifierType' => $identifierType,
            'ResultURL' => $this->config->get('MPESA_B2C_RESULT_URL'),
            'QueueTimeOutURL' => $this->config->get('MPESA_B2C_TIMEOUT_URL'),
            'Remarks' => $remarks,
            'Occasion' => '',
            'Timestamp' => $this->generateTimestamp()
        ];

        $endpoint = $this->config->isProduction()
            ? $this->config->get('MPESA_TRANSACTION_STATUS_URL_PRODUCTION')
            : $this->config->get('MPESA_TRANSACTION_STATUS_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }
}