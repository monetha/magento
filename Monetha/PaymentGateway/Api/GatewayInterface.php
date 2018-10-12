<?php
namespace Monetha\PaymentGateway\Api;

interface GatewayInterface
{
    /**
     * Process action send from monetha
     *
     * @return void
     */
    public function processAction();
}
