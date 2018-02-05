<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Monetha\PaymentGateway\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'monetha_gateway';

    protected $_method;
    protected $_storeManager;
    protected $_quote;
    protected $_checkoutSession;
    protected $_logger;


    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_method = $paymentHelper->getMethodInstance(self::CODE);
        $this->_storeManager = $storeManager;
        $this->_quote = $checkoutSession->getQuote();
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {

        $this->_checkoutSession->getQuote()->reserveOrderId();
        $oid = $this->_checkoutSession->getQuote()->getReservedOrderId();

        $mthApi = "https://api.monetha.io/";

        if ($this->_method->getConfigData("testmode")) {
            $mthApi = "https://api-sandbox.monetha.io/";
        }

        $items = [];

        $cartitems = $this->_quote->getAllItems();
        $sum = 0;
        foreach($cartitems as $item) {
            $li = [
                'name' => $item->getName(),
                'quantity' => $item->getQty(),
                'amount_fiat' => $item->getPrice()
            ];
            $sum = $sum + $item->getQty() * $item->getPrice();
            array_push($items, $li);
        }
        // Add shipping and taxes
        $shipping = [
            'name' => 'Shipping and taxes',
            'quantity' => 1,
            'amount_fiat' => $this->_quote->getGrandTotal() - $sum
        ];
        array_push($items, $shipping);

        $req = array(
            'deal' => array(
                'amount_fiat' => $this->_quote->getGrandTotal(),
                'currency_fiat' => $this->_storeManager->getStore()->getCurrentCurrency()->getCode(),
                'line_items' => $items
            ),
            'return_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'callback_url' => 'https://www.monetha.io/callback',
            'cancel_url' => 'https://www.monetha.io/cancel',
            'external_order_id' => $oid." ",
        );

        $this->_logger->info(json_encode($req));

        // Verify order information with Monetha
        $chSign = curl_init();
        curl_setopt_array($chSign, array(
            CURLOPT_URL => $mthApi . "v1/merchants/offer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($req, JSON_NUMERIC_CHECK),
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "MTH-Deal-Signature: " . $this->_method->getConfigData("merchant_key") . ":" . $this->_method->getConfigData("merchant_secret")
            )
        ));

        $res = curl_exec($chSign);
        $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);
        $resJson = json_decode($res);
        $this->_logger->info(json_encode($resStatus));
        $this->_logger->info($this->_method->getConfigData("merchant_key") . ":" . $this->_method->getConfigData("merchant_secret"));
        $this->_logger->info(json_encode($resJson));

    
        if ($resStatus && $resStatus >= 400 ) {
            $message = 'can not create an order - merchant invalid. Merchant must first signup at Monetha';
            $this->_logger->error($message);
            curl_close($chSign);
            return [
                'payment' => [
                    self::CODE => [
                        'monetha_api' => '',
                        'monetha_token' => '',
                        'monetha_error' => $resJson->error
                    ]
                ]
            ];
        } else {
            curl_close($chSign);
            if ($resJson && $resJson->token) {
                return [
                    'payment' => [
                        self::CODE => [
                            'monetha_api' => $mthApi,
                            'monetha_token' => $resJson->token,
                            'monetha_error' => ''
                        ]
                    ]
                ];
            } else {
                $message = 'can not create an order - could not retrieve deal token';
                $this->_logger->error($message);
                return [
                    'payment' => [
                        self::CODE => [
                            'monetha_api' => '',
                            'monetha_token' => '',
                            'monetha_error' => $message
                        ]
                    ]
                ];
            }
        }
    }
}