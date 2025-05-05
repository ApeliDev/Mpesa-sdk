# M-Pesa PHP Library

This library provides a simple and flexible way to integrate M-Pesa API services into your PHP application. It supports STK Push, C2B, B2C, transaction status queries, and reversals.

---

## Features
- **STK Push**: Initiate Lipa Na M-Pesa Online payments.
- **C2B**: Handle customer-to-business transactions.
- **B2C**: Process business-to-customer payments.
- **Transaction Status**: Query the status of transactions.
- **Reversal**: Reverse transactions.

---

## Installation

Install the library via Composer:

```bash
composer require apelimpesa/mpesa
```

---

## Configuration

### Step 1: Copy the Example Environment File

The library includes an `.env.example` file with all required configuration variables. Copy this file to your project root as `.env`:

```bash
cp vendor/apelimpesa/mpesa/.env.example .env
```

### Step 2: Configure Your Environment Variables

Edit the `.env` file with your M-Pesa API credentials and settings:

```ini
# ==============================================
# M-PESA API CONFIGURATION
# ==============================================

# Application Environment (development/production)
APP_ENV=development

# M-Pesa API Credentials
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=your_shortcode
MPESA_PASSKEY=your_passkey

# Security Credentials
MPESA_INITIATOR_NAME=your_initiator_name
MPESA_INITIATOR_PASSWORD=your_initiator_password
MPESA_CERTIFICATE_PATH=path/to/your/cert.pem

# ==============================================
# CALLBACK URLS
# ==============================================

# C2B URLs
MPESA_C2B_VALIDATION_URL=https://yourdomain.com/api/c2b/validate
MPESA_C2B_CONFIRMATION_URL=https://yourdomain.com/api/c2b/confirm

# B2C URLs
MPESA_B2C_RESULT_URL=https://yourdomain.com/api/b2c/result
MPESA_B2C_TIMEOUT_URL=https://yourdomain.com/api/b2c/timeout

# Transaction Status URLs
MPESA_TRANSACTION_STATUS_RESULT_URL=https://yourdomain.com/api/status/result
MPESA_TRANSACTION_STATUS_TIMEOUT_URL=https://yourdomain.com/api/status/timeout

# Reversal URLs
MPESA_REVERSAL_RESULT_URL=https://yourdomain.com/api/reversal/result
MPESA_REVERSAL_TIMEOUT_URL=https://yourdomain.com/api/reversal/timeout
```

### Step 3: Required Variables

| Variable                     | Description                                      | Required For          |
|------------------------------|--------------------------------------------------|-----------------------|
| `APP_ENV`                    | Application environment (`development`/`production`) | All operations        |
| `MPESA_CONSUMER_KEY`         | Your M-Pesa API consumer key                    | All operations        |
| `MPESA_CONSUMER_SECRET`      | Your M-Pesa API consumer secret                 | All operations        |
| `MPESA_SHORTCODE`            | Your business shortcode                         | All operations        |
| `MPESA_PASSKEY`              | Your Lipa Na M-Pesa Online passkey              | STK Push              |
| `MPESA_INITIATOR_NAME`       | B2C/B2B transaction initiator name              | B2C/B2B               |
| `MPESA_INITIATOR_PASSWORD`   | Encrypted initiator password                    | B2C/B2B               |
| `MPESA_CERTIFICATE_PATH`     | Path to your M-Pesa certificate (if required)   | Optional for security |

---

## Usage

### Step 1: Load Environment Variables

Ensure your application loads the `.env` file. For non-Laravel projects, use `vlucas/phpdotenv`:

```php
require_once __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

### Step 2: Initialize the Library

Create an instance of the M-Pesa service:

```php
use ApeliMpesa\Mpesa\Mpesa;

$mpesa = new Mpesa([
    'consumer_key' => getenv('MPESA_CONSUMER_KEY'),
    'consumer_secret' => getenv('MPESA_CONSUMER_SECRET'),
    'shortcode' => getenv('MPESA_SHORTCODE'),
    'passkey' => getenv('MPESA_PASSKEY'),
    'environment' => getenv('APP_ENV'), // 'development' or 'production'
]);
```

### Step 3: Perform Transactions

#### 1. STK Push (Lipa Na M-Pesa Online)

```php
$response = $mpesa->stkPush([
    'amount' => 100,
    'phone' => '+254712345678',
    'reference' => 'Order123',
    'description' => 'Payment for Order123',
    'callback_url' => 'https://yourdomain.com/api/stk/callback',
]);

if ($response->isSuccessful()) {
    echo "STK Push initiated successfully. CheckoutRequestID: " . $response->getCheckoutRequestID();
} else {
    echo "Error: " . $response->getErrorMessage();
}
```

#### 2. Query Transaction Status

```php
$response = $mpesa->queryTransactionStatus('CheckoutRequestID123');

if ($response->isSuccessful()) {
    echo "Transaction completed successfully.";
} else {
    echo "Error: " . $response->getErrorMessage();
}
```

#### 3. Reverse a Transaction

```php
$response = $mpesa->reverseTransaction([
    'transaction_id' => 'TransactionID123',
    'amount' => 100,
    'receiver' => '600000',
    'receiver_type' => 'Paybill',
    'callback_url' => 'https://yourdomain.com/api/reversal/callback',
]);

if ($response->isSuccessful()) {
    echo "Transaction reversed successfully.";
} else {
    echo "Error: " . $response->getErrorMessage();
}
```

---

## Testing with Sandbox

When `APP_ENV=development`, the library uses M-Pesa's sandbox environment. No real money is transferred during testing.

For production, set:

```ini
APP_ENV=production
```

---

## Security Notes

- Use different credentials for development and production environments.
- Keep your `.env` file secure and avoid committing it to version control.

---

## Requirements

- PHP 7.4 or higher
- Composer
- cURL extension enabled

---

## License

This library is open-source and available under the [MIT License](LICENSE).