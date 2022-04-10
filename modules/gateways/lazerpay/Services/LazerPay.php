<?php

namespace Cloudinos\WHMCS\Services;

use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;

define("LAZER_MODULE_NAME", basename(__DIR__));

class LazerPay
{
    protected $params = [];
    public function __construct() {
        $this->params = getGatewayVariables(LAZER_MODULE_NAME);
    }

    /*
     * Get callback secret and SystemURL to form the callback URL
     */
    public function getCallbackUrl()
    {
        return rtrim($this->getSystemUrl(), '/') . '/modules/gateways/lazerpay/verify-payment.php';
    }

    /*
     * Get user configured API key from database
     */
    public function getPublicKey()
    {
        $publicKey = $this->params['testNetPublicKey'];
        if (!$this->isTestNet()) {
            $publicKey = $this->params['mainNetPublicKey'];
        }

        return $publicKey;
    }

    /**
     * Checks if test mode is turned ON
     *
     * @return bool
     */
    public function isTestNet() {
        return $this->params['testMode'] === 'on';
    }

    /**
     * Gets invoice with currency. This is used to balance currency on
     * WHMCS and LazerPay.
     *
     * @param $invoiceId
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInvoiceByIdWithCurrency($invoiceId) {
        return Capsule::table('tblinvoices')
            ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
            ->leftJoin('tblcurrencies', 'tblcurrencies.id', '=', 'tblclients.currency')
            ->select([
                'tblinvoices.invoicenum',
                'tblinvoices.id',
                'tblclients.currency',
                'tblcurrencies.code as currency_code'])
            ->where('tblinvoices.id', $invoiceId)
            ->first();
    }

    /*
     * Get URL of the WHMCS installation
     */
    public function getSystemUrl()
    {
        return $this->params['systemurl'];
    }

    /**
     * Gets the transaction with reference in the url
     *
     * @param $reference
     * @param $publicKey
     *
     * @return array|bool|float|int|object|string|null
     */
    public function getTransaction($reference)
    {
        try {
            return \GuzzleHttp\json_decode(
                $this->setPublicKeyClient()->request('GET', $this->getVerifyTransactionUrl($reference)
                )->getBody()
                    ->getContents()
            );
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Sets public key to be used for HTTP request
     *
     * @return \GuzzleHttp\Client
     */
    public function setPublicKeyClient()
    {
        return new Client(['headers' => ['x-api-key' => trim($this->getPublicKey())]]);
    }


    /**
     * The verify URI from Lazerpay's docs
     *
     * @param $reference
     *
     * @return string
     */
    public function getVerifyTransactionUrl($reference)
    {
        return 'https://api.lazerpay.engineering/api/v1/transaction/verify/' . rawurlencode($reference);
    }

    /**
     * Checks convert to Currency is set on WHMCS admin for LazerPay
     *
     * @return mixed
     */
    public function convertToIsEnabled()
    {
        return $this->params['convertto'];
    }

    /**
     * Gets an invoice URL
     *
     * @param $invoiceId
     *
     * @return string
     */
    public function getInvoiceUrl($invoiceId)
    {
        return rtrim($this->getSystemUrl(), '/') . "/viewinvoice.php?id={$invoiceId}";
    }

    /**
     * Loads invoice page
     *
     * @param $invoiceId
     */
    public function loadInvoicePage($invoiceId)
    {
        header('Location: ' . $this->getInvoiceUrl($invoiceId));
        exit();
    }

    /**
     * Loads micro config for the module.
     *
     * @throws \JsonException
     */
    public function getConfig()
    {
        return json_decode(file_get_contents(__DIR__ . '/config.json'), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Checks LazerPay is active
     *
     * @return mixed
     */
    public function isActive() {
        return $this->params['type'];
    }

    /**
     * Checks LazerPay is not active
     *
     * @return bool
     */
    public function isNotActive()
    {
        return !$this->params['type'];
    }
}