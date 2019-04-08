<?php

namespace Monetha\PaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Monetha\Adapter\ConfigAdapterInterface;
use Monetha\ConfigAdapterTrait;
use Magento\Store\Model\ScopeInterface;
use Monetha\Services\GatewayService;

class OrderCancelAfter implements ObserverInterface, ConfigAdapterInterface
{
    use ConfigAdapterTrait;

    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethod();
        if ($paymentMethod == 'monetha_gateway') {
            $this->cancelExternalOrder($payment);
        }

        return $this;
    }

    public function cancelExternalOrder($payment)
    {
        try {
            $this->merchantSecret = $this->scopeConfig->getValue('payment/monetha_gateway/merchant_secret', ScopeInterface::SCOPE_STORE);
            $this->monethaApiKey = $this->scopeConfig->getValue('payment/monetha_gateway/mth_api_key', ScopeInterface::SCOPE_STORE);
            $this->testMode = $this->scopeConfig->getValue('payment/monetha_gateway/testmode');

            $externalOrderId = $payment->getAdditionalInformation('external_order_id');

            if (isset($externalOrderId)) {
                $gs = new GatewayService($this);
                $gs->cancelExternalOrder($externalOrderId);
            }
        } catch (\Exception $ex) {
        }
    }
}
