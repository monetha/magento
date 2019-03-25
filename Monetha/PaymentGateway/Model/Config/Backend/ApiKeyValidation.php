<?php

namespace Monetha\PaymentGateway\Model\Config\Backend;

use Monetha\PaymentGateway\Consts\ApiType;
use Magento\Framework\Exception\ValidatorException;
use Monetha\PaymentGateway\Services\HttpService;
use Monetha\PaymentGateway\Helpers\JWT;

class ApiKeyValidation extends \Magento\Framework\App\Config\Value
{
    public function beforeSave()
    {
        $gatewayActive = (bool)$this->getData('groups/monetha_gateway/fields/active');

        if ($gatewayActive == 1) {
            $merchantSecret = $this->getData('groups/monetha_gateway/fields/merchant_secret')['value'];
            $monethaApiKey = $this->getData('groups/monetha_gateway/fields/mth_api_key')['value'];

            if ($merchantSecret == '') {
                throw new ValidatorException(__('Merchant secret is required.'));
            }

            if ($monethaApiKey == '') {
                throw new ValidatorException(__('Monetha Api Key is required.'));
            }

            $testMode = $this->getData('groups/monetha_gateway/fields/testmode')['value'];
            $merchantId = $this->getMerchantId($monethaApiKey);

            if (!$this->validateApiToken($merchantId, $merchantSecret, $monethaApiKey, $testMode)) {
                throw new ValidatorException(__('Merchant secret or Monetha Api Key is not valid.'));
            }
        }

        $this->setValue($this->getValue());

        parent::beforeSave();
    }

    public function validateApiToken($merchantId, $secret, $apiKey, $testMode)
    {
        $apiUrl = ApiType::PROD;

        if ($testMode == 1) {
            $apiUrl = ApiType::TEST;
        }

        $apiUrl = $apiUrl . 'v1/merchants/' . $merchantId .'/secret';

        $response = HttpService::callApi($apiUrl, 'GET', null, ["Authorization: Bearer " . $apiKey]);
        if(isset($response->integration_secret))
        {
            return $response->integration_secret == $secret;
        }
        return false;
    }

    public function getMerchantId($apiKey)
    {
        $tks = explode('.', $apiKey);
        if (count($tks) != 3) {
            throw new ValidatorException(__('Invalid Api key!'));
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;

        if($this->isJson(JWT::urlsafeB64Decode($bodyb64)))
        {
            $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64));
        }
        else
        {
            throw new ValidatorException(__('Merchant secret or Monetha Api Key is not valid.'));
        }

        if (isset($payload->mid)) {
            return $payload->mid;
        }

        throw new ValidatorException(__('Invalid Api key!'));
    }

    public function isJson($str) {
        $json = json_decode($str);
        return $json && $str != $json;
    }
}
