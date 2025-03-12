<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class Frequency implements OptionSourceInterface
{
    /**
     * @return array<string, string|Phrase>[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('Hourly'),
                'value' => 'hourly',
            ],
            [
                'label' => __('Daily'),
                'value' => 'daily'
            ],
            [
                'label' => __('Custom'),
                'value' => 'custom'
            ],
        ];
    }
}
