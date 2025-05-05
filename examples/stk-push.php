<?php
/**
 * M-Pesa STK Push Example
 * 
 * This example demonstrates how to use the M-Pesa library to initiate an STK Push request
 * and then query its status.
 */

// Include autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Dotenv to load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Import the Mpesa class
use ApeliMpesa\Mpesa\Mpesa;

try {
    // Create an instance of the M-Pesa library
    $mpesa = new Mpesa();
    
    echo "=== M-Pesa STK Push Example ===\n\n";
    
    // Set up the STK Push parameters
    $response = $mpesa->stkPush()
        ->setPhoneNumber('254712345678') 
        ->setAmount(1) 
        ->setReference('ORDER123456')
        ->setDescription('Payment for order #123456')
        ->send();
    
    // Check if the request was successful
    if ($response->isSuccessful()) {
        $checkoutRequestId = $response->getCheckoutRequestID();
        
        echo "STK Push initiated successfully!\n";
        echo "Checkout Request ID: " . $checkoutRequestId . "\n";
        echo "Waiting for customer to complete the payment on their phone...\n\n";
        
        // Wait for a moment to allow the customer to respond to the STK push
        sleep(30);
        
        // Query the status of the transaction
        echo "Checking transaction status...\n";
        $statusResponse = $mpesa->stkPush()->query($checkoutRequestId);
        
        if ($statusResponse->isCompleted()) {
            echo "Success! Payment completed.\n";
            echo "Result Description: " . $statusResponse->getResultDescription() . "\n";
            echo "Raw Response: " . json_encode($statusResponse->raw, JSON_PRETTY_PRINT) . "\n";
        } elseif ($statusResponse->isPending()) {
            echo "Payment is still pending. The customer hasn't responded yet.\n";
        } else {
            echo "Payment failed or was cancelled.\n";
            echo "Result Description: " . $statusResponse->getResultDescription() . "\n";
        }
    } else {
        echo "Failed to initiate STK Push: " . $response->getErrorMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}