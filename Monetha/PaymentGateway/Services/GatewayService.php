<?php

namespace Monetha\PaymentGateway\Services;

use Monetha\Adapter\ConfigAdapterInterface;
use Monetha\Adapter\WebHookAdapterAbstract;
use Monetha\ConfigAdapterTrait;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Monetha\Services\GatewayService as GS;

class GatewayService extends WebHookAdapterAbstract implements ConfigAdapterInterface
{
    use ConfigAdapterTrait;

    protected $store;
    private $config;
    protected $orderManagement;
    protected $invoiceService;
    protected $invoiceSender;
    protected $registry;
    protected $transaction;
    private $orderApi;

    private $order;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderManagementInterface $orderManagement,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Api\Data\OrderInterface $orderApi
    ) {
        $this->config = $scopeConfig;
        $this->orderManagement = $orderManagement;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->registry = $registry;
        $this->orderApi = $orderApi;

        $this->merchantSecret = $this->config->getValue('payment/monetha_gateway/merchant_secret', ScopeInterface::SCOPE_STORE);
        $this->monethaApiKey = $this->config->getValue('payment/monetha_gateway/mth_api_key', ScopeInterface::SCOPE_STORE);
        $this->testMode = $this->config->getValue('payment/monetha_gateway/testmode');
    }

    public function cancelOrder($orderId)
    {
        $gs = new GS($this);
        $gs->cancelExternalOrder($orderId);
    }

    public function finalize()
    {
        $this->setInvoicePaid($this->order);
        $this->addOrderComment($this->order, 'Order has been successfully paid.');

        return true;
    }

    public function cancel($note)
    {
        $this->cancelOrderInvoice($this->order);
        $this->order->setState('canceled');
        $this->order->setStatus("canceled");
        $this->order->save();
        $this->addOrderComment($this->order, $note);

        return true;
    }

    public function authorize()
    {
        $this->setInvoicePaid($this->order);
        $this->addOrderComment($this->order, 'Order has been successfully paid by card.');

        return true;
    }

    public function setOrder($orderId)
    {
        $order = $this->orderApi->loadByIncrementId(trim($orderId, ' '));

        if (isset($order)) {
            $this->order = $order;

            return;
        }

        throw new \Exception(__('Order not found!'));
    }

    public function addOrderCommentById($orderId, $comment)
    {
        if (!empty($comment)) {
            $objectManager = ObjectManager::getInstance();
            $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    private function addOrderComment($order, $comment)
    {
        if (!empty($comment)) {
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    private function setInvoicePaid($order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->pay();
            $invoice->save();
        }
    }

    private function cancelOrderInvoice($order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->canCancel()) {
                $invoice->cancel();
                $invoice->save();
                $order->save();
            }
        }
    }
}
