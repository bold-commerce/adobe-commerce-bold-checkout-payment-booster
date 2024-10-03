<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;

/**
 * Is Bold Payment active value handler.
 */
class IsActiveValueHandler implements ValueHandlerInterface
{
    /**
     * @var State
     */
    private $state;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @param State $state
     * @param CheckoutData $checkoutData
     */
    public function __construct(State $state, CheckoutData $checkoutData)
    {
        $this->state = $state;
        $this->checkoutData = $checkoutData;
    }

    /**
     * @inheirtDoc
     */
    public function handle(array $subject, $storeId = null)
    {
        try {
            if ($this->state->getAreaCode() === Area::AREA_FRONTEND) {
                return $this->checkoutData->getPublicOrderId() !== null
                    && !$this->checkoutData->getQuote()->getIsMultiShipping();
            }
        } catch (LocalizedException $e) {
            return false;
        }
        return true;
    }
}
