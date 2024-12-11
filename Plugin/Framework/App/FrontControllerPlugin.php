<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Framework\App;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Closure;
use Exception;
use Magento\Framework\App\FrontController;
use Magento\Framework\App\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterListInterface;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

use function in_array;
use function strtolower;

class FrontControllerPlugin
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var RouterListInterface
     */
    private $routerList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
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
     * @param CheckoutData $checkoutData
     * @param RouterListInterface $routerList
     * @param LoggerInterface $logger
     */
    public function __construct(CheckoutData $checkoutData, RouterListInterface $routerList, LoggerInterface $logger)
    {
        $this->checkoutData = $checkoutData;
        $this->routerList = $routerList;
        $this->logger = $logger;
    }

    /**
     * @param FrontController $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     * @return Http|ResultInterface
     */
    public function aroundDispatch(FrontController $subject, Closure $proceed, RequestInterface $request)
    {
        $fullActionName = '';

        foreach ($this->routerList as $router) {
            $actionInstance = $router->match($request);

            if (!$actionInstance) {
                continue;
            }

            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();

            $fullActionName = $moduleName . '_' . $controllerName . '_' . $actionName;

            if ($fullActionName === '__') {
                continue;
            }

            break;
        }

        if (!in_array(strtolower($fullActionName), $this->allowedActions)) {
            return $proceed($request);
        }

        try {
            $this->checkoutData->initCheckoutData();
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }

        return $proceed($request);
    }
}
