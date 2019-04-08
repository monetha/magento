<?php
namespace Monetha\PaymentGateway\Model;

use \Magento\Framework\Webapi\Rest\Request;
use Monetha\PaymentGateway\Api\GatewayInterface;
use Monetha\PaymentGateway\Services\GatewayService;
use Monetha\Response\Exception\ValidationException;

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
        $bodyString = $this->request->getContent();
        $data = json_decode($bodyString);
        $this->gatewayService->setOrder($data->payload->external_order_id);

        try {
            $result = $this->gatewayService->processWebHook($this->gatewayService, $bodyString, $signature);
        } catch(ValidationException $e) {
            throw new \Magento\Framework\Exception\InputException(__($e->getMessage()));
        }

        if ($result) {
            return [
                'message' => 'e-shop healthy'
            ];
        }

        throw new \Magento\Framework\Exception\InputException(__('Something went wrong.'));
    }
}
