<?php


/**
/ ********************************************************************* \
 *                                                                      *
 *   LazerPay Payment Gateway                                           *
 *   Version: 1.0                                                       *
 *   Build Date: 10 April 2022                                          *
 *   Developer: Cloudinos                                               *
 *                                                                      *
 ************************************************************************
 *                                                                      *
 *   Email: dev@cloudinos.com                                           *
 *   Website: https://www.cloudinos.com                                  *
 *                                                                      *
\ ********************************************************************* /
 **/

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/Services/LazerPay.php';

use Cloudinos\WHMCS\Services\LazerPay;

$lazerPay = new LazerPay();

try {
    $config = $lazerPay->getConfig();
} catch (JsonException $e) {
    die('Failed to load config.');
}

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName = basename(__DIR__));

if ($lazerPay->isNotActive()) {
    die("Module Not Activated.");
}

// Retrieve data returned in payment gateway callback
$invoiceId = filter_input(INPUT_GET, "invoice_id");
$reference = filter_input(INPUT_GET, "trxref");
$isTestNet = $lazerPay->isTestNet();

if(!$isTestNet) {
    $secretKey = $gatewayParams['mainNetSecretKey'];
}

$publicKey = $gatewayParams['testNetPublicKey'];
if (!$isTestNet) {
    $publicKey = $gatewayParams['mainNetPublicKey'];
}

$transactionResponse = $lazerPay->getTransaction($reference);

if (!isset($transactionResponse->status)) {
    die("Failed to verify transaction at the moment. 
        Kindly send an email with invoice ID and reference to 
        <b>{$config->contacts->billing->email}.</b>"
    );
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 */
checkCbTransID($reference);

$amount = (float)$transactionResponse->data->amountPaid;
if ($lazerPay->convertToIsEnabled()) {
    $invoice = $lazerPay->getInvoiceByIdWithCurrency($invoiceId);

    $paymentCurrency = $transactionResponse->data->currency;
    if ($paymentCurrency !== $invoice->currency_code) {
        $invoiceCurrencyId = $invoice->currency;

        $convertToAmount = convertCurrency($amount, $gatewayParams['convertto'], $invoiceCurrencyId);
        $amount = format_as_currency($convertToAmount);
    }
}


/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
$paymentStatus = $transactionResponse->status === 'success' ? 'Successful' : 'Unsuccessful';
logTransaction($gatewayModuleName, $_GET, $paymentStatus);

$paymentFee = 0;


/**
 * Add Invoice Payment.
 *
 * Applies a payment transaction entry to the given invoice ID.
 *
 * @param int $invoiceId         Invoice ID
 * @param string $reference  Transaction Reference ID
 * @param float $amount   Amount paid (defaults to full balance)
 * @param float $paymentFee      Payment fee (optional)
 * @param string $gatewayModule  Gateway module name
 */
addInvoicePayment(
    $invoiceId,
    $reference,
    $amount,
    $paymentFee,
    $gatewayModuleName
);


/**
 * Display invoice to user.
 */
$lazerPay->loadInvoicePage($invoiceId);
