<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Adminhtml\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
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

    /** @var OrderExtensionDataFactory */
    private $orderExtensionDataFactory;

    /** @var OrderExtensionDataResource */
    private $orderExtensionDataResource;

    /** @var MagentoQuoteBoldOrderRepositoryInterfaceFactory  */
    private $magentoQuoteBoldOrderRepositoryFactory;

    /**
     * @param Context $context
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param LoggerInterface $logger
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param SetCompleteState $setCompleteState
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     * @param MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
     */
    public function __construct(
        Context                                         $context,
        CheckPaymentMethod                              $checkPaymentMethod,
        LoggerInterface                                 $logger,
        OrderExtensionDataRepository                    $orderExtensionDataRepository,
        OrderRepositoryInterface                        $orderRepository,
        RedirectFactory                                 $resultRedirectFactory,
        SetCompleteState                                $setCompleteState,
        OrderExtensionDataFactory                       $orderExtensionDataFactory,
        OrderExtensionDataResource                      $orderExtensionDataResource,
        MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
    ) {
        parent::__construct($context);
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->logger = $logger;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->orderRepository = $orderRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->setCompleteState = $setCompleteState;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
        $this->magentoQuoteBoldOrderRepositoryFactory = $magentoQuoteBoldOrderRepositoryFactory;
    }

    /**
     * Execute re-sync
     *
     * @return Redirect
     */
    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        if ($orderId) {
            try {
                /** @var Order $order */
                $order = $this->orderRepository->get($orderId);
                $quoteId = (int)$order->getQuoteId();
                $orderId = (int)$order->getId();
                $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($orderId);
                if (!$orderExtensionData->getPublicId()) {
                    $orderExtensionData = $this->savePublicId($quoteId, $orderId);
                }
                if ($this->checkPaymentMethod->isBold($order) && $orderExtensionData && $orderExtensionData->getPublicId()) {
                    $this->setCompleteState->execute($order);
                    $this->messageManager->addSuccessMessage((string)__('Bold order re-sync successful.'));
                }
            } catch (Exception $e) {
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

    /**
     * If order is not on order table, fetch from quote table using quote_id
     *
     * @param int $quoteId
     * @param int $orderId
     * @return OrderExtensionData|null
     */
    private function savePublicId(int $quoteId, int $orderId)
    {
        try {
            $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
            $magentoQuoteBoldOrder = $repository->getByQuoteId($quoteId);
            $publicOrderId = $magentoQuoteBoldOrder->getBoldOrderId();
            if ($publicOrderId !== null) {
                $orderExtensionData = $this->orderExtensionDataFactory->create();
                $orderExtensionData->setOrderId($orderId);
                $orderExtensionData->setPublicId($publicOrderId);
                $this->orderExtensionDataResource->save($orderExtensionData);
                return $orderExtensionData;
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        return null;
    }
}
