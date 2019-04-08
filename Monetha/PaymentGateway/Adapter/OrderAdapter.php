<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-28
 * Time: 13:40
 */

namespace Monetha\PaymentGateway\Adapter;


use Magento\Store\Api\Data\StoreInterface;
use Monetha\Adapter\CallbackUrlInterface;
use Monetha\Adapter\OrderAdapterInterface as MonethaOrderAdapterInterface;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;

class OrderAdapter implements MonethaOrderAdapterInterface, CallbackUrlInterface
{
    /**
     * @var OrderAdapterInterface
     */
    private $order;

    /**
     * @var StoreInterface
     */
    private $store;

    public function __construct(OrderAdapterInterface $order, StoreInterface $store)
    {
        $this->order = $order;
        $this->store = $store;
    }

    public function getItems()
    {
        return $this->order->getItems();
    }

    public function getGrandTotalAmount()
    {
        return $this->order->getGrandTotalAmount();
    }

    public function getCurrencyCode()
    {
        return $this->store->getCurrentCurrency()->getCode();
    }

    public function getCartId()
    {
        return $this->order->getOrderIncrementId();
    }

    public function getBaseUrl()
    {
        return $this->store->getBaseUrl();
    }

    public function getCallbackUrl()
    {
        return $this->store->getBaseUrl() . 'rest/V1/monetha/action';
    }
}