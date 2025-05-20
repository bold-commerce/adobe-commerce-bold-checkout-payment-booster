<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;
use Exception;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Address\CustomerAddressDataProvider;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session;
USE Magento\Catalog\Model\Product\Type;
class ExpressPay implements ArgumentInterface
{
    private const CATALOG_PRODUCT_VIEW = 'catalog_product_view';
    private const CURRENT_PRODUCT_REGISTRY_NAME = 'current_product';

    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var PaymentBoosterConfigProvider
     */
    private $paymentBoosterConfigProvider;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerAddressDataProvider
     */
    private $customerAddressDataProvider;

    /**
     * @var RequestInterface
     */
    private $requestInterface;

    /** @var Registry */
    private $registry;

    /** @var Session */
    private $checkoutSession;

    /**
     * @param CompositeConfigProvider $configProvider
     * @param PaymentBoosterConfigProvider $paymentBoosterConfigProvider
     * @param CheckoutData $checkoutData
     * @param LoggerInterface $logger
     * @param HttpContext $httpContext
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAddressDataProvider $customerAddressDataProvider
     * @param RequestInterface $requestInterface
     * @param Registry $registry
     */
    public function __construct(
        CompositeConfigProvider $configProvider,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider,
        CheckoutData $checkoutData,
        LoggerInterface $logger,
        HttpContext $httpContext,
        CustomerRepositoryInterface $customerRepository,
        CustomerAddressDataProvider $customerAddressDataProvider,
        RequestInterface $requestInterface,
        Registry $registry,
        Session $checkoutSession
    ) {
        $this->configProvider = $configProvider;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
        $this->checkoutData = $checkoutData;
        $this->logger = $logger;
        $this->httpContext = $httpContext;
        $this->customerRepository = $customerRepository;
        $this->customerAddressDataProvider = $customerAddressDataProvider;
        $this->requestInterface = $requestInterface;
        $this->registry = $registry;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Initialize checkout data and return the config.
     *
     * @return mixed[]
     */
    public function getCheckoutConfig(string $pageSource): array
    {
        try {
            $this->checkoutData->initCheckoutData();

            if ($pageSource === PaymentBoosterConfigProvider::PAGE_SOURCE_PRODUCT) {
                return $this->paymentBoosterConfigProvider->getConfigWithoutQuote();
            }

            return $this->configProvider->getConfig();
        } catch (Exception $e) {
            $this->logger->error('ExpressPay: ' . $e->getMessage());
            return [];
        }
    }

    public function isCustomerLoggedIn(): bool
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /**
     * @return mixed[]
     */
    public function getCustomerData(): array
    {
        $customerData = [];

        if (!$this->isCustomerLoggedIn()) {
            return $customerData;
        }

        try {
            /** @var CustomerInterface&Customer $customer */
            $customer = $this->getCustomer();
        } catch (LocalizedException $localizedException) {
            return $customerData;
        }

        $customerData = $customer->__toArray();

        try {
            $customerData['addresses'] = $this->customerAddressDataProvider->getAddressDataByCustomer($customer);
        } catch (LocalizedException $e) {
            $customerData['addresses'] = [];
        }

        return $customerData;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getCustomer(): CustomerInterface
    {
        /** @var int $customerId */
        $customerId = $this->httpContext->getValue('customer_id');

        return $this->customerRepository->getById($customerId);
    }

    /**
     * Check if current product is virtual
     *
     * @return bool
     */
    public function isDigitalGood(): bool
    {
        try {
            $product = $this->registry->registry(self::CURRENT_PRODUCT_REGISTRY_NAME);
            $currentPage = $this->requestInterface->getFullActionName();
            return (($currentPage === self::CATALOG_PRODUCT_VIEW) &&
                ($product->getTypeId()===Type::TYPE_VIRTUAL));
        } catch (LocalizedException $localizedException) {
            $this->logger->error('ExpressPay: ' . $localizedException->getMessage());
            return false;
        }
    }

    /**
     * Check if quote is virtual
     *
     * @return bool
     */
    public function isVirtualQuote() : bool
    {
        try {
            return $this->checkoutSession->getQuote()->isVirtual();
        } catch (LocalizedException $localizedException) {
            $this->logger->error('ExpressPay: ' . $localizedException->getMessage());
            return false;
        }
    }
}
