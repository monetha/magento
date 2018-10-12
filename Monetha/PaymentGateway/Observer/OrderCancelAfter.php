<?php

namespace Monetha\PaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;
use Monetha\PaymentGateway\Services\HttpService;
use Monetha\PaymentGateway\Consts\ApiType;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Exception\ValidatorException;

class OrderCancelAfter implements ObserverInterface
{
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
            $this->cancelExternalOrder($order, $payment);
        }

        return $this;
    }

    public function cancelExternalOrder($order, $payment)
    {
        try {
            $apiKey = $this->scopeConfig->getValue('payment/monetha_gateway/mth_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $testMode = $this->scopeConfig->getValue('payment/monetha_gateway/testmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $apiUrl = ApiType::PROD;

            if ($testMode == 1) {
                $apiUrl = ApiType::TEST;
            }

            $externalOrderId = $payment->getAdditionalInformation('external_order_id');

            if (isset($externalOrderId)) {
                $apiUrl = $apiUrl . 'v1/orders/' . $externalOrderId .'/cancel';
                $body = ['cancel_reason' => 'Order was cancelled from e-shop.'];
                $response = HttpService::callApi($apiUrl, 'POST', $body, ["Authorization: Bearer " . $apiKey]);
            }
        } catch (\Exception $ex) {
        }
    }
}
