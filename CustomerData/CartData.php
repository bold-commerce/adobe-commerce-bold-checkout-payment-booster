<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\CustomerData;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface as ShippingMethodManager;
use Magento\Ui\Component\Form\Element\Multiline;

/**
 * Cart data provider for payment booster.
 */
class CartData implements SectionSourceInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ShippingMethodManager
     */
    private $shippingMethodManager;

    /**
     * @var AddressMetadataInterface
     */
    private $addressMetadata;

    /**
     * @param Session $checkoutSession
     * @param ShippingMethodManager $shippingMethodManager
     * @param AddressMetadataInterface $addressMetadata
     */
    public function __construct(
        Session $checkoutSession,
        ShippingMethodManager $shippingMethodManager,
        AddressMetadataInterface $addressMetadata
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->shippingMethodManager = $shippingMethodManager;
        $this->addressMetadata = $addressMetadata;
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $cartData = [
            'billingAddress' => $this->getAddressData($quote->getBillingAddress()),
        ];
        if ($quote->isVirtual()) {
            return $cartData;
        }
        $cartData['shippingAddress'] = $this->getAddressData($quote->getShippingAddress());
        $cartData['shippingMethod'] = $this->getSelectedShippingMethod();
        return $cartData;
    }

    /**
     * Create address data appropriate to fill checkout address.
     *
     * @param AddressInterface $address
     * @return array
     */
    private function getAddressData(AddressInterface $address): array
    {
        try {
            $addressData = [];
            $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
            foreach ($attributesMetadata as $attributeMetadata) {
                if (!$attributeMetadata->isVisible()) {
                    continue;
                }
                $attributeCode = $attributeMetadata->getAttributeCode();
                $attributeData = $address->getData($attributeCode);
                if ($attributeData) {
                    if ($attributeMetadata->getFrontendInput() === Multiline::NAME) {
                        $attributeData = \is_array($attributeData) ? $attributeData : explode("\n", $attributeData);
                        $attributeData = (object)$attributeData;
                    }
                    if ($attributeMetadata->isUserDefined()) {
                        $addressData[CustomAttributesDataInterface::CUSTOM_ATTRIBUTES][$attributeCode] = $attributeData;
                        continue;
                    }
                    $addressData[$attributeCode] = $attributeData;
                }
            }
        } catch (LocalizedException $e) {
            $addressData = [];
        }
        return $addressData;
    }

    /**
     * Get selected shipping method data.
     *
     * @return array|null
     */
    private function getSelectedShippingMethod(): ?array
    {
        try {
            $quoteId = $this->checkoutSession->getQuote()->getId();
            $shippingMethod = $this->shippingMethodManager->get($quoteId);
            if ($shippingMethod) {
                return $shippingMethod->__toArray();
            }
            return null;
        } catch (Exception $exception) {
            return null;
        }
    }
}
