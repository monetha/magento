<?php

namespace Monetha\PaymentGateway\Consts;

class EventType
{
    const CANCELLED = 'order.canceled';
    const FINALIZED = 'order.finalized';
    const PING = 'order.ping';
    const MONEY_AUTHORIZED = 'order.money_authorized';
}
