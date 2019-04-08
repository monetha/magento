<?php

namespace Monetha\PaymentGateway\Model\Config\Backend;

use Monetha\Adapter\ConfigAdapterInterface;
use Monetha\ConfigAdapterTrait;
use Magento\Framework\Exception\ValidatorException;
use Monetha\Response\Exception\ApiException;
use Monetha\Services\GatewayService;

class ApiKeyValidation extends \Magento\Framework\App\Config\Value implements ConfigAdapterInterface
{
    use ConfigAdapterTrait;

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

            $this->merchantSecret = $merchantSecret;
            $this->monethaApiKey = $monethaApiKey;
            $this->testMode = $this->getData('groups/monetha_gateway/fields/testmode')['value'];

            $gc = new GatewayService($this);

            try {
                $result = $gc->validateApiKey();
            } catch(ApiException $e) {
                $result = false;
            }

            if (!$result) {
                throw new ValidatorException(__('Merchant secret or Monetha Api Key is not valid.'));
            }
        }

        $this->setValue($this->getValue());

        parent::beforeSave();
    }
}
