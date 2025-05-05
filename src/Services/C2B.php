<?php

namespace ApeliMpesa\Mpesa\Services;

use ApeliMpesa\Mpesa\Config\MpesaConfig;
use ApeliMpesa\Mpesa\Http\MpesaClient;
use ApeliMpesa\Mpesa\Exceptions\MpesaException;
use ApeliMpesa\Mpesa\Exceptions\InvalidRequestException;
use DateTime;

/**
 * C2B Service
 * 
 * Handles Customer to Business (C2B) transactions including:
 * - Transaction simulation (for testing)
 * - URL registration for live transactions
 * - Transaction status queries
 */
class C2B
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
     * Supported C2B command IDs
     */
    const COMMAND_IDS = [
        'CustomerPayBillOnline' => 'CustomerPayBillOnline',
        'CustomerBuyGoodsOnline' => 'CustomerBuyGoodsOnline'
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
     * Simulate C2B transaction (for sandbox/testing)
     * 
     * @param array $transactionDetails {
     *     @var string $CommandID       Transaction type
     *     @var string $Amount          Transaction amount
     *     @var string $Msisdn          Customer phone number (2547...)
     *     @var string $BillRefNumber   Bill reference number
     *     @var string $ShortCode       Business shortcode
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function simulateTransaction(array $transactionDetails): array
    {
        $this->validateSimulationDetails($transactionDetails);

        $payload = $this->buildSimulationPayload($transactionDetails);

        $endpoint = $this->config->get('MPESA_C2B_SIMULATE_URL_PRODUCTION');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Register validation and confirmation URLs
     * 
     * @param array $urlDetails {
     *     @var string $ValidationURL   URL for validation callback
     *     @var string $ConfirmationURL URL for confirmation callback
     *     @var string $ResponseType    Response type (Completed/Cancelled)
     *     @var string $ShortCode       Business shortcode
     * }
     * 
     * @return array M-Pesa API response
     * @throws InvalidRequestException If validation fails
     * @throws MpesaException If API request fails
     */
    public function registerUrls(array $urlDetails): array
    {
        $this->validateUrlDetails($urlDetails);

        $payload = $this->buildRegistrationPayload($urlDetails);

        $endpoint = $this->config->isProduction()
            ? $this->config->get('MPESA_C2B_REGISTER_URL_PRODUCTION')
            : $this->config->get('MPESA_C2B_REGISTER_URL_SANDBOX');

        return $this->client->post($endpoint, $payload);
    }

    /**
     * Validate C2B simulation details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validateSimulationDetails(array $details): void
    {
        $required = [
            'CommandID',
            'Amount',
            'Msisdn',
            'BillRefNumber',
            'ShortCode'
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

        if (!preg_match('/^2547\d{8}$/', $details['Msisdn'])) {
            throw InvalidRequestException::invalidValue('Msisdn', $details['Msisdn'], ['2547XXXXXXXX']);
        }
    }

    /**
     * Validate URL registration details
     * 
     * @param array $details
     * @throws InvalidRequestException
     */
    protected function validateUrlDetails(array $details): void
    {
        $required = [
            'ValidationURL',
            'ConfirmationURL',
            'ResponseType',
            'ShortCode'
        ];

        foreach ($required as $field) {
            if (empty($details[$field])) {
                throw InvalidRequestException::missingParameter($field);
            }
        }

        $validResponseTypes = ['Completed', 'Cancelled'];
        if (!in_array($details['ResponseType'], $validResponseTypes)) {
            throw InvalidRequestException::invalidValue(
                'ResponseType',
                $details['ResponseType'],
                $validResponseTypes
            );
        }
    }

    /**
     * Build simulation payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildSimulationPayload(array $details): array
    {
        return [
            'CommandID' => $details['CommandID'],
            'Amount' => $details['Amount'],
            'Msisdn' => $details['Msisdn'],
            'BillRefNumber' => $details['BillRefNumber'],
            'ShortCode' => $details['ShortCode'] ?? $this->config->get('MPESA_SHORTCODE')
        ];
    }

    /**
     * Build URL registration payload
     * 
     * @param array $details
     * @return array
     */
    protected function buildRegistrationPayload(array $details): array
    {
        return [
            'ValidationURL' => $details['ValidationURL'],
            'ConfirmationURL' => $details['ConfirmationURL'],
            'ResponseType' => $details['ResponseType'],
            'ShortCode' => $details['ShortCode'] ?? $this->config->get('MPESA_SHORTCODE'),
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
     * Get default callback URLs from config
     * 
     * @return array
     */
    public function getDefaultCallbackUrls(): array
    {
        return [
            'ValidationURL' => $this->config->get('MPESA_C2B_VALIDATION_URL'),
            'ConfirmationURL' => $this->config->get('MPESA_C2B_CONFIRMATION_URL')
        ];
    }

    /**
     * Get supported C2B command IDs
     * 
     * @return array
     */
    public function getSupportedCommandIds(): array
    {
        return self::COMMAND_IDS;
    }
}