<?php
namespace Monetha\PaymentGateway\Model;

use \Magento\Framework\Webapi\Rest\Request;
use Monetha\PaymentGateway\Api\GatewayInterface;
use Monetha\PaymentGateway\Services\GatewayService;
use Monetha\PaymentGateway\Consts\EventType;

class Gateway implements GatewayInterface
{
    protected $request;
    protected $gatewayService;
    protected $jsonHelper;

    public function __construct(
         Request $request,
         GatewayService $gatewayService,
         \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->request = $request;
        $this->gatewayService = $gatewayService;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * Process action send from monetha
     *
     * @return string
     */
    public function processAction()
    {
        $signature = $this->request->getHeader('mth_signature');
        $body = $this->request->getContent();
        $data = json_decode($body);

        if ($data->event == EventType::PING) {
            return [
                'message' => 'e-shop healthy'
            ];
        }

        if ($this->gatewayService->validateSignature($signature, $body)) {
            return $this->gatewayService->processAction($data);
        } else {
            throw new \Magento\Framework\Exception\InputException(__('Bad signature'));
        }
    }
}
