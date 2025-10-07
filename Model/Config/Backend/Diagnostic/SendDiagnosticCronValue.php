<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Backend\Diagnostic;

use Bold\CheckoutPaymentBooster\Model\Config\Backend\AbstractCronExpressionValue;

class SendDiagnosticCronValue extends AbstractCronExpressionValue
{
    public const CRON_EXPRESSION_PATH =
        'crontab/default/jobs/bold_checkout_payment_booster_diagnostic/schedule/cron_expression';
    public const CRON_EXPRESSION_CONFIG_GROUP_PATH =
        'checkout/bold_checkout_payment_booster_diagnostic/';
    public const CRON_EXPRESSION_CONFIG_GROUP_CONFIG_FREQUENCY = 'frequency';
    public const CRON_EXPRESSION_CONFIG_GROUP_CONFIG_OCCURRENCE = 'occurrence';
    public const CRON_EXPRESSION_CONFIG_GROUP_CONFIG_SEQUENCE = 'sequence';

    /**
     * Get cron expression path
     *
     * @return string
     */
    public function getCronExpressionPath(): string
    {
        return self::CRON_EXPRESSION_PATH;
    }

    /**
     * Get config group path
     *
     * @return string
     */
    public function getCronConfigurationGroupPath(): string
    {
        return self::CRON_EXPRESSION_CONFIG_GROUP_PATH;
    }

    /**
     * Get frequency path
     *
     * @return string
     */
    public function getCronConfigurationFrequencyPath(): string
    {
        return self::CRON_EXPRESSION_CONFIG_GROUP_CONFIG_FREQUENCY;
    }

    /**
     * Get occurrence path
     *
     * @return string
     */
    public function getCronConfigurationOccurrencePath(): string
    {
        return self::CRON_EXPRESSION_CONFIG_GROUP_CONFIG_OCCURRENCE;
    }

    /**
     * Get sequence path
     *
     * @return string
     */
    public function getCronConfigurationSequencePath(): string
    {
        return self::CRON_EXPRESSION_CONFIG_GROUP_CONFIG_SEQUENCE;
    }
}
