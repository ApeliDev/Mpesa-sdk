<?php

namespace ApeliMpesa\Mpesa\Helpers;

class ValidationHelper
{
    /**
     * Validate a phone number for M-Pesa transactions
     *
     * @param string $phoneNumber The phone number to validate
     * @return bool True if the phone number is valid, false otherwise
     */
    public static function validatePhoneNumber(string $phoneNumber): bool
    {
        // Check if the phone number starts with the correct country code
        return preg_match('/^\+254\d{9}$/', $phoneNumber) === 1;
    }

    /**
     * Validate an amount for M-Pesa transactions
     *
     * @param float|int $amount The amount to validate
     * @return bool True if the amount is valid, false otherwise
     */
    public static function validateAmount($amount): bool
    {
        return is_numeric($amount) && $amount > 0;
    }

    /**
     * Validate a callback URL
     *
     * @param string $url The URL to validate
     * @return bool True if the URL is valid, false otherwise
     */
    public static function validateCallbackUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate a timestamp in YYYYMMDDHHmmss format
     *
     * @param string $timestamp The timestamp to validate
     * @return bool True if the timestamp is valid, false otherwise
     */
    public static function validateTimestamp(string $timestamp): bool
    {
        return preg_match('/^\d{14}$/', $timestamp) === 1;
    }
}