<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Monetha\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\ValidatorException;
use Monetha\Adapter\ConfigAdapterInterface;
use Monetha\ConfigAdapterTrait;
use Monetha\PaymentGateway\Adapter\ClientAdapter;
use Monetha\PaymentGateway\Adapter\OrderAdapter;
use Monetha\PaymentGateway\Services\GatewayService;
use Monetha\Response\Exception\ApiException;
use Monetha\Services\GatewayService as GS;

class AuthorizationRequest implements BuilderInterface, ConfigAdapterInterface
{
    use ConfigAdapterTrait;

    const CODE = 'monetha_gateway';

    protected $store;
    private $config;
    private $gatewayService;

    public function __construct(
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        GatewayService $gatewayService
    ) {
        $this->config = $config;
        $this->monethaApiKey = $this->config->getValue('mth_api_key');
        $this->merchantSecret = $this->config->getValue('merchant_secret');
        $this->testMode = $this->config->getValue('testmode');
        $this->store = $storeManager->getStore();
        $this->gatewayService = $gatewayService;
    }

    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new ValidatorException(__('Payment data object should be provided'));
        }

        try {
            $paymentDO = $buildSubject['payment'];
            $order = $paymentDO->getOrder();

            $gs = new GS($this);

            $orderAdapter = new OrderAdapter($order, $this->store);
            $clientAdapter = new ClientAdapter($order->getBillingAddress());
            $executeOfferResponse = $gs->getExecuteOfferResponse($orderAdapter, $clientAdapter);

            $paymentUrl = $executeOfferResponse->getPaymentUrl();
            $payment = $paymentDO->getPayment();
            $payment->setAdditionalInformation('paymentUrl', $paymentUrl);
            $payment->setAdditionalInformation('external_order_id', $executeOfferResponse->getOrderId());
        } catch (ApiException $e) {
            $message = sprintf(
                'Status code: %s, error: %s, message: %s',
                $e->getApiStatusCode(),
                $e->getApiErrorCode(),
                $e->getMessage()
            );
            error_log($message);
            throw new ValidatorException(__($e->getFriendlyMessage()));

        } catch (\Exception $e) {
            throw new ValidatorException(__($e->getMessage()));
        }

        $address = $order->getShippingAddress();

        return [
            'TXN_TYPE' => 'A',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'EMAIL' => $address ? $address->getEmail() : null,
            'MERCHANT_KEY' => $this->config->getValue(
                'mth_api_key',
                $order->getStoreId()
            ),
            'PAYMENT_URL' => $paymentUrl,
        ];
    }
}
