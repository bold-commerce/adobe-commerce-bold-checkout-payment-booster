<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Adminhtml\Order;

use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Retry extends Action
{
    /** @var RedirectFactory */
    protected $resultRedirectFactory;

    /** @var CheckPaymentMethod */
    private $checkPaymentMethod;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderExtensionDataRepository */
    private $orderExtensionDataRepository;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var SetCompleteState */
    private $setCompleteState;

    /**
     * @param Context $context
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param LoggerInterface $logger
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param SetCompleteState $setCompleteState
     */
    public function __construct(
        Context                      $context,
        CheckPaymentMethod           $checkPaymentMethod,
        LoggerInterface              $logger,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        OrderRepositoryInterface     $orderRepository,
        RedirectFactory              $resultRedirectFactory,
        SetCompleteState             $setCompleteState
    ) {
        parent::__construct($context);
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->logger = $logger;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->orderRepository = $orderRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->setCompleteState = $setCompleteState;
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if ($orderId) {
            try {
                /** @var Order $order */
                $order = $this->orderRepository->get($orderId);
                $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($orderId);
                if ($this->checkPaymentMethod->isBold($order) && $orderExtensionData->getPublicId()) {
                    $this->setCompleteState->execute($order);
                    $this->messageManager->addSuccessMessage((string) __('Bold order re-sync successful.'));
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $this->messageManager->addErrorMessage(__('Error: ') . $e->getMessage());
            }
        }
        return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * Acl isAllowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Sales::actions_view');
    }
}
