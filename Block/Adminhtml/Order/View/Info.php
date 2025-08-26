<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\Order\View;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

class Info extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /** @var Registry */
    private $registry;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $repository;

    /** @var MagentoQuoteBoldOrderInterface|null */
    private $orderInfo = null;

    /** @var Config  */
    private $config;

    /** @var string  */
    protected $_template = 'order/view/tab/bold_order_info.phtml';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param MagentoQuoteBoldOrderRepositoryInterface $repository
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        MagentoQuoteBoldOrderRepositoryInterface $repository,
        Config $config,
        array $data = []
    ) {
        $this->registry   = $registry;
        $this->repository = $repository;
        $this->config     = $config;
        parent::__construct($context, $data);
    }

    /**
     * Only show for Bold payment methods.
     */
    public function isShowInfo(): bool
    {
        $order = $this->getOrder();
        if (!$order || !$order->getPayment()) {
            return false;
        }

        $method = (string)$order->getPayment()->getMethod();
        return in_array($method, ['bold', 'bold_wallet', 'bold_fastlane'], true);
    }

    /**
     * Get current order from registry (no side effects).
     */
    public function getOrder(): ?OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->registry->registry('current_order');
        return $order;
    }

    /**
     * Load Bold order info by quote_id (no recursion).
     */
    public function getOrderInfo(): ?MagentoQuoteBoldOrderInterface
    {
        if ($this->orderInfo !== null) {
            return $this->orderInfo;
        }

        $order = $this->registry->registry('current_order'); // ← no call to getOrder()
        if (!$order || !$order->getQuoteId()) {
            return null;
        }

        try {
            $this->orderInfo = $this->repository->getByQuoteId((int)$order->getQuoteId());
        } catch (NoSuchEntityException $e) {
            $this->orderInfo = null;
        } catch (\Throwable $e) {
            $this->_logger->error('[Bold Admin Info] ' . $e->getMessage());
            $this->orderInfo = null;
        }

        return $this->orderInfo;
    }

    /**
     * Retrieves updated order information and sets necessary data on the order entity.
     *
     * @return OrderInterface|null Returns the updated order object with additional data set,
     * or null if the order or order information is not available.
     */
    public function getInfo()
    {
        $order = $this->getOrder();
        $info  = $this->getOrderInfo();

        if (!$order || !$info) {
            return null;
        }

        // Use setData to avoid needing hard setters on the order entity.
        $order->setData('successful_auth_full_at', $info->getSuccessfulAuthFullAt());
        $order->setData('successful_hydrate_at', $info->getSuccessfulHydrateAt());
        $order->setData('successful_state_at', $info->getSuccessfulStateAt());
        $order->setData('public_order_id', $info->getBoldOrderId());

        return $order ;
    }

    /**
     * Retrieve the label for the tab.
     *
     * @return Phrase The label for the tab.
     */
    public function getTabLabel()
    {
        return __('Bold Order Information');
    }

    /**
     * Retrieves the title for the tab.
     *
     * @return Phrase The localized title for the tab.
     */
    public function getTabTitle()
    {
        return __('Bold Order Information');
    }

    /**
     * Determines if the tab can be displayed.
     *
     * @return bool True if the tab can be shown, false otherwise.
     */
    public function canShowTab()
    {
        $order = $this->getOrder();
        $method = $order->getPayment()->getMethod();

        $boldMethods = [
            'bold',
            'bold_wallet',
            'bold_fastlane'
        ];

        return $this->config->isShowSalesOrderViewTab((int) $order->getStore()->getWebsiteId())
            && in_array($method, $boldMethods, true);
    }

    /**
     * Checks whether the item is hidden.
     *
     * @return bool Returns false to indicate the item is not hidden.
     */
    public function isHidden()
    {
        return false;
    }
}
