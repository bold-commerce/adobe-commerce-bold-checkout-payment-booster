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

class ExpressPay implements ArgumentInterface
{
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
     * @param CompositeConfigProvider $configProvider
     * @param PaymentBoosterConfigProvider $paymentBoosterConfigProvider
     * @param CheckoutData $checkoutData
     * @param LoggerInterface $logger
     * @param HttpContext $httpContext
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAddressDataProvider $customerAddressDataProvider
     */
    public function __construct(
        CompositeConfigProvider $configProvider,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider,
        CheckoutData $checkoutData,
        LoggerInterface $logger,
        HttpContext $httpContext,
        CustomerRepositoryInterface $customerRepository,
        CustomerAddressDataProvider $customerAddressDataProvider
    ) {
        $this->configProvider = $configProvider;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
        $this->checkoutData = $checkoutData;
        $this->logger = $logger;
        $this->httpContext = $httpContext;
        $this->customerRepository = $customerRepository;
        $this->customerAddressDataProvider = $customerAddressDataProvider;
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
}
