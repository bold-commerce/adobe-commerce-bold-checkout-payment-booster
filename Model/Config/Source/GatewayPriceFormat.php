<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class GatewayPriceFormat implements OptionSourceInterface
{
    public const LEGACY_MODE = 'legacy';
    public const INCLUDE_TAX = 'include_tax';
    public const EXCLUDE_TAX = 'exclude_tax';

    /**
     * @return array<string, string|Phrase>[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('Legacy'),
                'value' => self::LEGACY_MODE
            ],
            [
                'label' => __('Send product prices excluding tax (this will add a product item with the amount)'),
                'value' => self::EXCLUDE_TAX,
            ],
            [
                'label' => __('Send product prices including tax'),
                'value' => self::INCLUDE_TAX,
            ],
        ];
    }
}
