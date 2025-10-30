<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote;

use Exception;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;

use function __;

/**
 * Service class for updating quotes.
 *
 * @api
 */
class Update
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CartTotalRepositoryInterface
     */
    private $quoteTotalRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var QuoteIdMaskResource
     */
    private $quoteIdMaskResource;

    /**
     * @var ShipmentEstimationInterface
     */
    private $shipmentEstimation;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param CartTotalRepositoryInterface $quoteTotalRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CartTotalRepositoryInterface $quoteTotalRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource,
        ShipmentEstimationInterface $shipmentEstimation,
        RegionFactory $regionFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteTotalRepository = $quoteTotalRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->regionFactory = $regionFactory;
    }

    /**
     * Load quote by masked ID.
     *
     * @param string $maskId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function loadQuoteByMaskId(string $maskId): CartInterface
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResource->load($quoteIdMask, $maskId, 'masked_id');

            if (!$quoteIdMask->getQuoteId()) {
                throw new NoSuchEntityException(
                    __('No quote found with mask ID "%1"', $maskId)
                );
            }

            $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());

            if (!$quote->getIsActive()) {
                throw new LocalizedException(
                    __('Quote with ID "%1" is not active', $maskId)
                );
            }

            return $quote;
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('Could not load quote. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Update customer information on quote.
     *
     * @param CartInterface $quote
     * @param array<string, string> $customerData
     * @return CartInterface
     * @throws LocalizedException
     */
    public function updateCustomerInfo(CartInterface $quote, array $customerData): CartInterface
    {
        try {
            /** @var Quote $quote */
            if (isset($customerData['email'])) {
                $quote->setCustomerEmail($customerData['email']);
                // Also set email on addresses for guest quotes
                if ($quote->getBillingAddress()) {
                    $quote->getBillingAddress()->setEmail($customerData['email']);
                }
                if ($quote->getShippingAddress()) {
                    $quote->getShippingAddress()->setEmail($customerData['email']);
                }
            }

            if (isset($customerData['firstname'])) {
                $quote->setCustomerFirstname($customerData['firstname']);
            }

            if (isset($customerData['lastname'])) {
                $quote->setCustomerLastname($customerData['lastname']);
            }

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not update customer info. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Update billing address on quote.
     *
     * @param CartInterface $quote
     * @param array<string, mixed> $addressData
     * @return CartInterface
     * @throws LocalizedException
     */
    public function updateBillingAddress(CartInterface $quote, array $addressData): CartInterface
    {
        try {
            $billingAddress = $quote->getBillingAddress();
            $this->updateAddressData($billingAddress, $addressData);
            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not update billing address. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Update shipping address on quote.
     *
     * @param CartInterface $quote
     * @param array<string, mixed> $addressData
     * @return CartInterface
     * @throws LocalizedException
     */
    public function updateShippingAddress(CartInterface $quote, array $addressData): CartInterface
    {
        try {
            /** @var Quote $quote */
            $shippingAddress = $quote->getShippingAddress();
            $this->updateAddressData($shippingAddress, $addressData);
            
            // Enable shipping rate collection when shipping address is updated
            $shippingAddress->setCollectShippingRates(true);

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not update shipping address. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Update address data with provided fields.
     *
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @param array<string, mixed> $addressData
     * @return void
     */
    private function updateAddressData(\Magento\Quote\Api\Data\AddressInterface $address, array $addressData): void
    {
        if (isset($addressData['firstname'])) {
            $address->setFirstname($addressData['firstname']);
        }

        if (isset($addressData['lastname'])) {
            $address->setLastname($addressData['lastname']);
        }

        if (isset($addressData['street'])) {
            $address->setStreet($addressData['street']);
        }

        if (isset($addressData['city'])) {
            $address->setCity($addressData['city']);
        }

        // Handle region and region_id with automatic lookup
        if (isset($addressData['region']) || isset($addressData['region_id'])) {
            $regionId = $addressData['region_id'] ?? null;
            $regionName = $addressData['region'] ?? null;
            
            // If region name is provided but region_id is not, look up the region_id
            if ($regionName && !$regionId && isset($addressData['country_id'])) {
                $region = $this->regionFactory->create();
                $region->loadByName($regionName, $addressData['country_id']);
                if ($region->getId()) {
                    $regionId = $region->getId();
                }
            }
            
            if ($regionName) {
                $address->setRegion($regionName);
            }
            if ($regionId) {
                $address->setRegionId($regionId);
            }
        }

        if (isset($addressData['postcode'])) {
            $address->setPostcode($addressData['postcode']);
        }

        if (isset($addressData['country_id'])) {
            $address->setCountryId($addressData['country_id']);
        }

        if (isset($addressData['telephone'])) {
            $address->setTelephone($addressData['telephone']);
        }

        if (isset($addressData['email'])) {
            $address->setEmail($addressData['email']);
        }
    }

    /**
     * Set shipping method on quote.
     *
     * @param CartInterface $quote
     * @param string $carrierCode
     * @param string $methodCode
     * @return CartInterface
     * @throws LocalizedException
     */
    public function setShippingMethod(CartInterface $quote, string $carrierCode, string $methodCode): CartInterface
    {
        try {
            /** @var Quote $quote */
            $shippingAddress = $quote->getShippingAddress();
            
            if (!$shippingAddress || !$shippingAddress->getCountryId()) {
                throw new LocalizedException(
                    __('Cannot set shipping method. Quote does not have a valid shipping address.')
                );
            }

            if ($quote->getIsVirtual()) {
                throw new LocalizedException(
                    __('Cannot set shipping method. Quote contains only virtual products.')
                );
            }

            // Validate that the shipping method is available for this quote
            $availableMethods = $this->getAvailableShippingMethods($quote);
            $shippingMethodCode = $carrierCode . '_' . $methodCode;
            $isMethodAvailable = false;
            
            foreach ($availableMethods as $method) {
                $availableMethodCode = $method->getCarrierCode() . '_' . $method->getMethodCode();
                if ($availableMethodCode === $shippingMethodCode) {
                    $isMethodAvailable = true;
                    break;
                }
            }
            
            if (!$isMethodAvailable) {
                throw new LocalizedException(
                    __(
                        'Shipping method "%1" is not available for this quote. Please use the Quote Update API to retrieve available shipping methods.',
                        $shippingMethodCode
                    )
                );
            }

            // Set the shipping method using carrier_code and method_code
            $shippingAddress->setShippingMethod($shippingMethodCode);
            
            // Request fresh shipping rate collection
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->collectShippingRates();

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not set shipping method. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Get available shipping methods for quote.
     *
     * @param CartInterface $quote
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[]
     */
    public function getAvailableShippingMethods(CartInterface $quote): array
    {
        if ($quote->getIsVirtual() || $quote->getItemsCount() == 0) {
            return [];
        }

        /** @var Quote $quote */
        $shippingAddress = $quote->getShippingAddress();
        
        if (!$shippingAddress || !$shippingAddress->getCountryId()) {
            return [];
        }

        try {
            // Use Magento's ShipmentEstimation service to get properly formatted shipping methods
            return $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $shippingAddress);
        } catch (\Exception $e) {
            // Return empty array if estimation fails
            return [];
        }
    }

    /**
     * Save quote.
     *
     * @param CartInterface $quote
     * @return CartInterface
     * @throws LocalizedException
     */
    public function saveQuote(CartInterface $quote): CartInterface
    {
        try {
            /** @var Quote $quote */
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
            // Reload quote to get updated data including proper serialization
            $quote = $this->quoteRepository->get($quote->getId());
            
            // Ensure customer data is available in the response for guest quotes
            // by re-applying from addresses if needed
            /** @var Quote $quote */
            if ($quote->getCustomerIsGuest() && !$quote->getCustomerEmail()) {
                $billingAddress = $quote->getBillingAddress();
                if ($billingAddress && $billingAddress->getEmail()) {
                    $quote->setCustomerEmail($billingAddress->getEmail());
                    $quote->setCustomerFirstname($billingAddress->getFirstname());
                    $quote->setCustomerLastname($billingAddress->getLastname());
                }
            }
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not save quote. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }

        return $quote;
    }

    /**
     * Get quote totals.
     *
     * @param CartInterface $quote
     * @return \Magento\Quote\Api\Data\TotalsInterface
     * @throws LocalizedException
     */
    public function getQuoteTotals(CartInterface $quote): \Magento\Quote\Api\Data\TotalsInterface
    {
        try {
            $totals = $this->quoteTotalRepository->get($quote->getId());
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not get quote totals. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }

        return $totals;
    }
}

