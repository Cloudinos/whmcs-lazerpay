<?php /** @noinspection PhpMissingReturnTypeInspection */
/**
 * WHMCS layer Payment Gateway Module
 * @developer: Cloudinos
 * @phpVersion: 5.6+
 *
 * This module allow businesses receive or accept crypto on
 * their web hosting WHMCS platforms
 *
 * If want to modify this file, do ensure you test your code properly
 * before creating a Pull Request.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/ for guides
 *
 * @copyright Copyright (c) Cloudinos Limited 2022
 * @license https://github.com/Cloudinos/whmcs-lazerpay/blob/main/LICENSE
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define LazerPay metadata
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function lazerpay_MetaData()
{
    return [
        'DisplayName' => 'Pay with Crypto - LazerPay',
        'APIVersion' => '1.1', // Use API Version 1.1 as recommended by WHMCS team.
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * Define LazerPay configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 * @return array
 */
function lazerpay_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'LazerPay (Crypto)',
        ],
        // a text field type allows for single line text input
        'testNetSecretKey' => [
            'FriendlyName' => 'Testnet Secret Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter testnet secret key',
        ],
        'testNetPublicKey' => [
            'FriendlyName' => 'Testnet Public Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter testnet public key',
        ],
        'mainNetSecretKey' => [
            'FriendlyName' => 'Mainnet Secret Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter mainnet secret key',
        ],
        'mainNetPublicKey' => [
            'FriendlyName' => 'Mainnet Public Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter mainnet public key',
        ],
        'callbackUrl' => [
            'FriendlyName' => 'Callback URL',
            'Value' => "https://{$_SERVER['HTTP_HOST']}/modules/gateways/lazerpay/verify-payment.php",
            'Type' => 'text',
            'Description' => 'The webhook URL for mainnet ',
        ],
        'testMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ],

    ];
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 */
function lazerpay_link($params)
{
    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstName = $params['clientdetails']['firstname'];
    $lastName = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleName = $params['paymentmethod'];

    $txnref = 'CLDLP_' . $invoiceId . '_' . time();
    $name = $firstName . ' ' . $lastName;
    $supportedCurrencies = ["USD", "NGN", "AED", "EUR", "GBP"];
    $callbackUrl = $params['callbackUrl'];

    /*
     * Return an error if user selected currency isn't supported by Lazerpay/
     * @see https://docs.lazerpay.finance/home/payments/accept-payments
     */
    if (!in_array($currencyCode, $supportedCurrencies, false)) {
        return "<div 
            class='label label-lg label-danger' 
            style='max-width: 100% !important; 
            white-space: inherit'
            >Selected($currencyCode) currency isn't supported.</div>";
    }

    $isTestnet = $params['testMode'] !== '';
    $publicKey = $params['testNetPublicKey'];
    if (!$isTestnet) {
        $publicKey = $params['mainNetPublicKey'];
    }

    /**
     * We used input type instead of a button becauce WHMCS stype this
     * by default. There is no extra styles needed from us.
     */
    $htmlOutput = '<input type="submit" id="lazerPaymentBtn" value="' . $langPayNow . '" />';
    $htmlOutput .= '<script src="https://cdn.jsdelivr.net/gh/LazerPay-Finance/checkout-build@main/checkout@1.0.1/dist/index.min.js"></script>';

    $htmlOutput .= '<script>
        const paymentForm = document.getElementById("lazerPaymentBtn");
        paymentForm.addEventListener("click", payWithLazerpay, false);
        
        function payWithLazerpay(e){
          e.preventDefault();
            
          LazerCheckout({
             reference: "' . $txnref . '",
             name: "' . $name . '",
             email: "' . $email . '",
             amount: "' . $amount . '",
             key: "' . $publicKey . '", 
             currency: "' . $currencyCode . '",
             acceptPartialPayment: false,
             onClose: (data) => console.info("Cancelled payment with LazerPay: " + new Date()),
             onSuccess: (data) => performCallbackAction("success"),
             onError: (data) => console.log(data)
          })
        }
        
        function performCallbackAction(status) {
            window.location.href = "' . addslashes($callbackUrl) . '?invoice_id='.$invoiceId.'&trxref=' . $txnref . '&status=" + status;
        }
    </script>';

    return $htmlOutput;
}

