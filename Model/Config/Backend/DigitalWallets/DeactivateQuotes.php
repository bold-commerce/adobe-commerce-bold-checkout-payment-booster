<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Backend\DigitalWallets;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

use function __;

class DeactivateQuotes extends Value
{
    public function beforeSave(): self
    {
        $frequency = $this->getValue();
        $occurrence = $this->getOccurrence();

        if ($frequency === 'hourly' && ($occurrence < 1 || $occurrence > 23)) {
            throw new LocalizedException(__('%1 is not a valid hourly occurrence', $occurrence));
        }

        if ($frequency === 'daily' && ($occurrence < 1 || $occurrence > 31)) {
            throw new LocalizedException(__('%1 is not a valid daily occurrence', $occurrence));
        }

        return parent::beforeSave();
    }

    private function getOccurrence(): int
    {
        if ($this->getFieldsetDataValue('occurrence') !== null) {
            return (int)$this->getFieldsetDataValue('occurrence');
        }

        // @phpstan-ignore-next-line
        return (int)(
            $this ->_config->getValue(
                'checkout/bold_checkout_payment_booster_advanced/digital_wallets_quote_cleanup_occurrence'
            ) ?? 1
        );
    }
}
