<?php

namespace Monetha\PaymentGateway\Services;

use Monetha\PaymentGateway\Consts\Resource;
use Monetha\PaymentGateway\Consts\EventType;
use Monetha\PaymentGateway\Consts\ApiType;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Monetha\PaymentGateway\Service\HttpService;

class GatewayService
{
    protected $store;
    private $config;
    private $merchantSecret;
    private $monethaApiKey;
    private $testMode;
    protected $orderManagement;
    protected $invoiceService;
    protected $invoiceSender;
    protected $registry;
    protected $transaction;
    private $orderApi;

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

    public function prepareOfferBody($order)
    {
        $items = [];
        $cartItems = $order->getItems();

        $itemsPrice = 0;
        foreach ($cartItems as $item) {
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
            if($price > 0)
            {
                $items[] = $li;
            }
        }

        $itemsPrice = round($itemsPrice, 2);

        $grandTotal = round($order->getGrandTotalAmount(), 2);

        // Add shipping and taxes
        $shipping = [
            'name' => 'Shipping and taxes',
            'quantity' => 1,
            'amount_fiat' => round($grandTotal - $itemsPrice, 2),
        ];

        if($shipping['amount_fiat'] > 0)
        {
            $items[] = $shipping;
        }

        $deal = array(
            'deal' => array(
                'amount_fiat' => $grandTotal,
                'currency_fiat' => $this->store->getCurrentCurrency()->getCode(),
                'line_items' => $items
            ),
            'return_url' => $this->store->getBaseUrl(),
            'callback_url' => $this->store->getBaseUrl() . 'rest/V1/monetha/action',
            'external_order_id' => $order->getOrderIncrementId() . " ",
        );
        return $deal;
    }

    public function validateSignature($signature, $data)
    {
        return $signature == base64_encode(hash_hmac('sha256', $data, $this->merchantSecret, true));
    }

    public function getApiUrl()
    {
        $apiUrl = ApiType::PROD;

        if ((bool)$this->testMode) {
            $apiUrl = ApiType::TEST;
        }

        return $apiUrl;
    }

    public function cancelOrder($orderId)
    {
        $apiUrl = $this->getApiUrl();
        $apiUrl = $apiUrl . 'v1/orders/' . $orderId .'/cancel';
        $body = ['cancel_reason'=> 'Order cancelled from shop'];
        return HttpService::callApi($apiUrl, 'POST', $body, ["Authorization: Bearer " . $this->mthApiKey]);
    }

    public function createClient($clientBody)
    {
        $apiUrl = $this->getApiUrl();
        $apiUrl = $apiUrl . 'v1/clients';

        return HttpService::callApi($apiUrl, 'POST', $clientBody, ["Authorization: Bearer " . $this->mthApiKey]);
    }

    public function createOffer($offerBody)
    {
        $apiUrl = $this->getApiUrl();
        $apiUrl = $apiUrl . 'v1/merchants/offer_auth';

        return HttpService::callApi($apiUrl, 'POST', $offerBody, ["Authorization: Bearer " . $this->mthApiKey]);
    }

    public function executeOffer($token)
    {
        $apiUrl = $this->getApiUrl();
        $apiUrl = $apiUrl . 'v1/deals/execute';

        return HttpService::callApi($apiUrl, 'POST', ["token" => $token], []);
    }

    public function processAction(\stdClass $data)
    {
        $order = $this->getOrder($data->payload->external_order_id);

        switch ($data->resource) {
            case Resource::ORDER:
                switch ($data->event) {
                    case EventType::CANCELLED:
                        $this->cancelOrderInvoice($order);
                        $order->setState('canceled');
                        $order->setStatus("canceled");
                        $order->save();
                        $this->addOrderComment($order, $data->payload->note);
                        break;
                    case EventType::FINALIZED:
                        $this->setInvoicePaid($order);
                        $this->addOrderComment($order, 'Order has been successfully paid.');
                        break;
                    case EventType::MONEY_AUTHORIZED:
                        $this->setInvoicePaid($order);
                        $this->addOrderComment($order, 'Order has been successfully paid by card.');
                        break;
                    default:
                        throw new \Magento\Framework\Exception\InputException(__('Bad event type!'));
                        break;
                }
                break;

            default:
            throw new \Magento\Framework\Exception\InputException(__('Bad resource type!'));
            break;
        }
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

    public function addOrderComment($order, $comment)
    {
        if (!empty($comment)) {
            $order->addStatusHistoryComment($comment);
            $order->save();
        }
    }

    public function getOrder($orderId)
    {
        $order = $this->orderApi->loadByIncrementId(trim($orderId, ' '));

        if (isset($order)) {
            return $order;
        } else {
            throw new \Exception(__('Order not found!'));
        }
    }

    public function setInvoicePaid($order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->pay();
            $invoice->save();
        }
    }

    public function cancelOrderInvoice($order)
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
