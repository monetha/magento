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
use Magento\Framework\Exception\ValidatorException;
use Monetha\PaymentGateway\Consts\ApiType;
use Monetha\PaymentGateway\Services\GatewayService;

class AuthorizationRequest implements BuilderInterface
{
    const CODE = 'monetha_gateway';

    protected $store;
    private $config;
    private $apiKey = '';
    private $testMode = '';
    private $merchantSecret = '';
    private $gatewayService;

    public function __construct(
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        GatewayService $gatewayService
    ) {
        $this->config = $config;
        $this->apiKey = $this->config->getValue('mth_api_key');
        $this->merchantSecret = $this->config->getValue('merchant_secret');
        $this->testMode = $this->config->getValue('testmode');
        $this->store = $storeManager->getStore();
        $this->gatewayService = $gatewayService;
    }

    private function callApi(string $uri, string $method = 'GET', array $body = null)
    {
        $mthApi = ApiType::PROD;
        if ($this->testMode) {
            $mthApi = ApiType::TEST;
        }

        $chSign = curl_init();

        $options = [
            CURLOPT_URL => $mthApi . $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>  array(
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->apiKey
            ),
        ];

        if ($method !== 'GET' && $body) {
            $client_uri = @end(explode('/',$uri));
            if($client_uri == 'clients') {
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
            } else {
                $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_NUMERIC_CHECK);
            }
            
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($chSign, $options);

        $res = curl_exec($chSign);
        $error = curl_error($chSign);
        $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);

        if (($resStatus >= 400)
            && isset($res)
            && isset(json_decode($res)->code)) {
            if(json_decode($res)->code == 'AMOUNT_TOO_BIG') {
                throw new ValidatorException(__('The value of your cart exceeds the maximum amount. Please remove some of the items from the cart.'));
            }
            if(json_decode($res)->code == 'AMOUNT_TOO_SMALL') {
                throw new ValidatorException(__('amount_fiat in body should be greater than or equal to 0.01'));
            }
            if(json_decode($res)->code == 'INVALID_PHONE_NUMBER') {
                throw new ValidatorException(__('Invalid phone number'));
            }
            if(json_decode($res)->code == 'AUTH_TOKEN_INVALID') {
                throw new ValidatorException(__('Monetha plugin setup is invalid, please contact merchant.'));
            }
            if(json_decode($res)->code == 'INTERNAL_ERROR') {
                throw new ValidatorException(__('There\'s some internal server error, please contact merchant.'));
            }
            if(json_decode($res)->code == 'UNSUPPORTED_CURRENCY') {
                throw new ValidatorException(__('Selected currency is not supported by monetha.'));
            }
            if(json_decode($res)->code == 'PROCESSOR_MISSING') {
                throw new ValidatorException(__('Can\'t process order, please contact merchant.'));
            }
            if(json_decode($res)->code == 'INVALID_PHONE_COUNTRY_CODE') {
                throw new ValidatorException(__('This country code is invalid, please input correct country code.'));
            }
        }

        if ($error) {
            throw new ValidatorException(__($error));
        }

        if ($resStatus < 200 || $resStatus >= 300) {
            throw new ValidatorException(__($res));
        }

        $resJson = json_decode($res);

        curl_close($chSign);

        return $resJson;
    }

    private function createDeal(OrderAdapterInterface $order): array
    {
        $items = [];
        $cartItems = $order->getItems();

        $itemsPrice = 0;
        foreach ($cartItems as $item) {
            /**
             * @var $item Interceptor
             */
            $price = round($item->getPrice(), 2);
            if (!$price) {
                continue;
            }
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
        if ($shipping['amount_fiat']) {
            $items[] = $shipping;
        }

        $client_id = 0;
        $billing_address = $order->getBillingAddress();

        $phoneNumber = preg_replace('/\D/', '', $billing_address->getTelephone());

        if($phoneNumber) {
            $client_body = array(
                "contact_name" => $billing_address->getFirstname() . " " .  $billing_address->getLastname(),
                "contact_email" => $billing_address->getEmail(),
                "contact_phone_number" => $phoneNumber,
                "country_code_iso" => $billing_address->getCountryId(),
                "zipcode" => $billing_address->getPostcode(),
                "city" => $billing_address->getCity(),
                "address" => $billing_address->getStreetLine1()
            );

            $resJson = $this->callApi("v1/clients", 'POST', $client_body);

            if(isset($resJson->client_id)) {
                $client_id = $resJson->client_id;
            }
        }

        $deal = array(
            'deal' => array(
                'amount_fiat' => $grandTotal,
                'currency_fiat' => $this->store->getCurrentCurrency()->getCode(),
                'line_items' => $items,
                'client_id' => $client_id
            ),
            'return_url' => $this->store->getBaseUrl(),
            'callback_url' => $this->store->getBaseUrl() . 'rest/V1/monetha/action',
            'external_order_id' => $order->getOrderIncrementId() . " ",
        );
        return $deal;
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
            $offerBody = $this->createDeal($order);
            $address = $order->getShippingAddress();
            $resJson = $this->callApi("v1/merchants/offer_auth", 'POST', $offerBody);
            $paymentUrl = '';
            $resJson = $this->callApi('v1/deals/execute?token=' . $resJson->token);
            $paymentUrl = $resJson->order->payment_url;
            $payment = $paymentDO->getPayment();
            $payment->setAdditionalInformation('paymentUrl', $paymentUrl);
            $payment->setAdditionalInformation('external_order_id', $resJson->order->id);
        } catch (\Exception $ex) {
            throw new ValidatorException(__($ex->getMessage()));
        }

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
