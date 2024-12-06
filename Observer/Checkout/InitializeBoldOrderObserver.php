<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize Bold order.
 */
class InitializeBoldOrderObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    private $allowedActions = [
        'cms_index_index',
        'cms_noroute_index',
        'cms_page_view',
        'catalog_category_view',
        'catalog_product_view',
        'checkout_cart_index',
        'checkout_index_index',
        'firecheckout_index_index',
        'customer_account_create',
        'customer_account_login',
        'customer_account_index',
        'customer_account_edit',
        'customer_account_logoutSuccess',
        'customer_account_confirmation',
        'customer_account_forgotpassword',
        'customer_account_createpassword',
        'customer_address_index',
        'customer_address_form',
        'sales_order_history',
        'sales_order_view',
        'downloadable_customer_products',
        'vault_cards_listaction',
        'review_customer_index',
        'newsletter_manage_index',
        'catalogsearch_result_index',
        'catalogsearch_advanced_index',
        'search_term_popular',
        'sales_guest_form',
        'contact_index_index',
        'wishlist_index_index'
    ];

    /**
     * @param LoggerInterface $logger
     * @param CheckoutData $checkoutData
     */
    public function __construct(
        LoggerInterface $logger,
        CheckoutData $checkoutData
    ) {
        $this->logger = $logger;
        $this->checkoutData = $checkoutData;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        $actionName = $request->getFullActionName();

        if (!in_array(strtolower($actionName), $this->allowedActions)) {
            return;
        }

        try {
            $this->checkoutData->initCheckoutData();
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }
    }
}
