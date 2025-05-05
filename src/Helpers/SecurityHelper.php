<?php

namespace ApeliMpesa\Mpesa\Helpers;

class SecurityHelper
{
    /**
     * Generate a secure password for M-Pesa API requests
     *
     * @param string $shortcode The business shortcode
     * @param string $passkey The M-Pesa passkey
     * @param string $timestamp The current timestamp in YYYYMMDDHHmmss format
     * @return string The base64-encoded password
     */
    public static function generatePassword(string $shortcode, string $passkey, string $timestamp): string
    {
        return base64_encode($shortcode . $passkey . $timestamp);
    }

    /**
     * Validate a signature against a given payload and secret
     *
     * @param string $payload The payload to validate
     * @param string $signature The provided signature
     * @param string $secret The secret key used to generate the signature
     * @return bool True if the signature is valid, false otherwise
     */
    public static function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $computedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Encode data to JSON and handle errors
     *
     * @param mixed $data The data to encode
     * @return string The JSON-encoded string
     * @throws \JsonException If encoding fails
     */
    public static function encodeJson($data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Decode JSON data and handle errors
     *
     * @param string $json The JSON string to decode
     * @param bool $assoc Whether to return an associative array
     * @return mixed The decoded data
     * @throws \JsonException If decoding fails
     */
    public static function decodeJson(string $json, bool $assoc = false)
    {
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }
}