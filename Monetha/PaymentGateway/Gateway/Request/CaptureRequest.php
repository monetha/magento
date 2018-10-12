<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Monetha\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Payment\Model\Method\Logger;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;
    private $logger;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        Logger $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        return [
            'TXN_TYPE' => 'S',
            'TXN_ID' => $payment->getLastTransId(),
            'MERCHANT_KEY' => $this->config->getValue(
                'mth_api_key',
                $order->getStoreId()
            )
        ];
    }
}
