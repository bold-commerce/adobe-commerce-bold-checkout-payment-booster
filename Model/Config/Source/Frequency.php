<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Source;

use Bold\CheckoutPaymentBooster\Model\Config\Backend\AbstractCronExpressionValue;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class Frequency implements OptionSourceInterface
{
    /**
     * Cron frequency options
     *
     * @return array<string, string|Phrase>[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('Hourly'),
                'value' => AbstractCronExpressionValue::CRON_EXPRESSION_FREQUENCY_HOURLY,
            ],
            [
                'label' => __('Daily'),
                'value' => AbstractCronExpressionValue::CRON_EXPRESSION_FREQUENCY_DAILY
            ],
            [
                'label' => __('Custom'),
                'value' => AbstractCronExpressionValue::CRON_EXPRESSION_FREQUENCY_CUSTOM
            ],
        ];
    }
}
