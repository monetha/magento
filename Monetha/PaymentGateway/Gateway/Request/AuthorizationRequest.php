<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Monetha\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Item\Interceptor;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;

class AuthorizationRequest implements BuilderInterface
{
    const CODE = 'monetha_gateway';

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var string
     */
    private $merchantKey = '';

    /**
     * @var string
     */
    private $testMode = '';

    /**
     * @var string
     */
    private $merchantSecret = '';

    public function __construct(
        ConfigInterface $config,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;

        $this->merchantKey = $this->config->getValue('merchant_key');
        $this->merchantSecret = $this->config->getValue('merchant_secret');
        $this->testMode = $this->config->getValue('testmode');

        $this->store = $storeManager->getStore();
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array|null $body
     *
     * @return mixed
     * @throws \Exception
     */
    private function callApi(string $uri, string $method = 'GET', array $body = null) {
        $mthApi = "https://api.monetha.io/mth-gateway/";
        if ($this->testMode) {
            $mthApi = "https://api-sandbox.monetha.io/mth-gateway/";
        }

        $chSign = curl_init();

        $options = [
            CURLOPT_URL => $mthApi . $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>  array(
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "MTH-Deal-Signature: " . $this->merchantKey . ":" . $this->merchantSecret
            ),
        ];

        if ($method !== 'GET' && $body) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_NUMERIC_CHECK);
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($chSign, $options);

        $res = curl_exec($chSign);
        $error = curl_error($chSign);

        if ($error) {
            //TODO: log
            throw new \Exception($error);
        }

        $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);
        if ($resStatus < 200 || $resStatus >= 300) {
            //TODO: log
            throw new \Exception($res);
        }

        $resJson = json_decode($res);

        curl_close($chSign);

        return $resJson;
    }

    private function createDeal(OrderAdapterInterface $order): array {
        $items = [];
        $cartItems = $order->getItems();

        $itemsPrice = 0;
        foreach($cartItems as $item) {
            /**
             * @var $item Interceptor
             */
            $price = round($item->getPrice(), 2);
            $quantity = $item->getQtyOrdered();
            $li = [
                'name' => $item->getName(),
                'quantity' => $quantity,
                'amount_fiat' => $price,
            ];
            $itemsPrice += $price * $quantity;
            $items[] = $li;
        }

        $itemsPrice = round($itemsPrice, 2);

        $grandTotal = round($order->getGrandTotalAmount(), 2);

        // Add shipping and taxes
        $shipping = [
            'name' => 'Shipping and taxes',
            'quantity' => 1,
            'amount_fiat' => round($grandTotal - $itemsPrice, 2),
        ];
        $items[] = $shipping;

        $deal = array(
            'deal' => array(
                'amount_fiat' => $grandTotal,
                'currency_fiat' => $this->store->getCurrentCurrency()->getCode(),
                'line_items' => $items
            ),
            'return_url' => $this->store->getBaseUrl(),
            'callback_url' => 'https://www.monetha.io/callback',
            'cancel_url' => 'https://www.monetha.io/cancel',
            'external_order_id' => $order->getOrderIncrementId() . " ",
        );

        return $deal;
    }

    /**
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /**
         * @var $paymentDO PaymentDataObjectInterface
         */
        $paymentDO = $buildSubject['payment'];
        $order = $paymentDO->getOrder();

        $deal = $this->createDeal($order);

        $address = $order->getShippingAddress();

        $resJson = $this->callApi("v1/merchants/offer", 'POST', $deal);
        $paymentUrl = '';
        if ($resJson && $resJson->token) {
            $resJson = $this->callApi('v1/deals/execute?token=' . $resJson->token);
            $paymentUrl = $resJson->order->payment_url;

            $payment = $paymentDO->getPayment();
            $payment->setAdditionalInformation('paymentUrl', $paymentUrl);
        }

        return [
            'TXN_TYPE' => 'A',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'EMAIL' => $address->getEmail(),
            'MERCHANT_KEY' => $this->config->getValue(
                'merchant_gateway_key',
                $order->getStoreId()
            ),
            'PAYMENT_URL' => $paymentUrl,
        ];
    }
}
