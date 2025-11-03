<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Quote\Model\QuoteManagement;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Transfer is_bold_integration_cart attribute from quote to order.
 */
class TransferIntegrationAttributePlugin
{
    /**
     * Transfer extension attribute from quote to order after order is submitted.
     *
     * @param QuoteManagement $subject
     * @param OrderInterface $result
     * @param CartInterface $quote
     * @return OrderInterface
     */
    public function afterSubmit(
        QuoteManagement $subject,
        OrderInterface $result,
        CartInterface $quote
    ): OrderInterface {
        $quoteExtension = $quote->getExtensionAttributes();
        $isBoldIntegrationCart = $quoteExtension && $quoteExtension->getIsBoldIntegrationCart();
        
        if ($isBoldIntegrationCart) {
            $orderExtension = $result->getExtensionAttributes();
            if ($orderExtension) {
                $orderExtension->setIsBoldIntegrationCart(true);
                $result->setExtensionAttributes($orderExtension);
            }
        }
        
        return $result;
    }
}

