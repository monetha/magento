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

    protected $method;
    protected $storeManager;
    protected $quote;


    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->method = $paymentHelper->getMethodInstance(self::CODE);
        $this->storeManager = $storeManager;
        $this->quote = $checkoutSession->getQuote();
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $items = [];

        $cartitems = $this->quote->getAllItems();

        foreach($cartitems as $item) {
            $item = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'quantity' => $item->getQty(),
                'price' => $item->getPrice(),
                'tax' => $item->getTaxAmount()
            ];
            array_push($items, $item);
        }

        return [
            'payment' => [
                self::CODE => [
                    'merchant_secret_id' => $this->method->getConfigData("merchant_secret_id"),
                    'merchant_project_id' => $this->method->getConfigData("merchant_project_id"),
                    'merchant_return_url' => $this->method->getConfigData("merchant_return_url"),                    
                    'cart' => [
                        'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                        'sub_total' => $this->quote->getSubtotal(),
                        'grand_total' => $this->quote->getGrandTotal(),
                        'items' => $items
                    ]
                ]
            ]
        ];
    }
}
