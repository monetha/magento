<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Monetha\PaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;

class OrderPlaceAfter implements ObserverInterface
{
    /**
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    public function __construct(
        ResponseFactory $responseFactory,
        UrlInterface $url,
        OrderRepository $orderRepository
    )
    {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
    }

    public function execute(Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();

        $paymentUrl = '';
        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            $paymentUrl = $payment->getAdditionalInformation('paymentUrl');
        }

        if ($paymentUrl) {
            $cartUrl = $this->url->getUrl($paymentUrl);
            $this->responseFactory->create()->setRedirect($cartUrl)->sendResponse();
            exit;
        }
    }
}
