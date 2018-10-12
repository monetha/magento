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
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class OrderPlaceAfter implements ObserverInterface
{
    protected $responseFactory;
    protected $url;
    protected $orderRepository;
    protected $invoiceService;
    protected $invoiceSender;
    protected $registry;
    protected $transaction;

    public function __construct(
        ResponseFactory $responseFactory,
        UrlInterface $url,
        OrderRepository $orderRepository,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->registry = $registry;
    }

    public function execute(Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();

        $paymentUrl = '';
        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            $paymentUrl = $payment->getAdditionalInformation('paymentUrl');

            // Prepare invoice
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();

            $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );

            $transactionSave->save();
        }

        if ($paymentUrl) {
            $cartUrl = $this->url->getUrl($paymentUrl);
            $this->responseFactory->create()->setRedirect($cartUrl)->sendResponse();
            exit;
        }
    }
}
