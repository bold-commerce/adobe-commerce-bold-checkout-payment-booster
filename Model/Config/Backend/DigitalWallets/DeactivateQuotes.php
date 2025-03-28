<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Backend\DigitalWallets;

use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

use function __;

class DeactivateQuotes extends Value
{
    public const CRON_EXPRESSION_PATH =
        'crontab/default/jobs/bold_booster_deactivate_digital_wallets_quotes/schedule/cron_expression';

    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $valueFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $valueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->valueFactory = $valueFactory;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

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

    public function afterSave(): self
    {
        $frequency = $this->getValue();
        $occurrence = $this->getOccurrence();
        $sequence = $this->getSequence();
        $cronExpression = '';

        if ($frequency === 'hourly') {
            $cronExpression = "0 */$occurrence * * *";
        }

        if ($frequency === 'daily') {
            $cronExpression = "0 0 */$occurrence * *";
        }

        if ($frequency === 'custom') {
            $cronExpression = $sequence;
        }

        try {
            $this->valueFactory->create()
                ->load(self::CRON_EXPRESSION_PATH, 'path')
                ->setValue($cronExpression)
                ->setPath(self::CRON_EXPRESSION_PATH)
                ->save();
        } catch (Exception $exception) {
            throw new LocalizedException(__('Could not save the Cron expression.'), $exception);
        }

        return parent::afterSave();
    }

    private function getOccurrence(): int
    {
        if ($this->getFieldsetDataValue('occurrence') !== null) {
            return (int)$this->getFieldsetDataValue('occurrence');
        }

        return (int)(
            $this ->_config->getValue(
                'checkout/bold_checkout_payment_booster_advanced/digital_wallets_quote_cleanup_occurrence'
            ) ?? 1
        );
    }

    private function getSequence(): string
    {
        if ($this->getFieldsetDataValue('sequence') !== null) {
            return $this->getFieldsetDataValue('sequence');
        }

        return (string)(
            $this ->_config->getValue(
                'checkout/bold_checkout_payment_booster_advanced/digital_wallets_quote_cleanup_sequence'
            ) ?? '* * * * *'
        );
    }
}
