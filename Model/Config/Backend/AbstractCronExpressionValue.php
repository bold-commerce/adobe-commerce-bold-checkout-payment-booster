<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Config\Backend;

use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriter;

use function __;

abstract class AbstractCronExpressionValue extends Value
{
    /**
     * Get expression config cron path
     */
    abstract public function getCronExpressionPath(): string;

    /**
     * Get expression config group path
     */
    abstract public function getCronConfigurationGroupPath(): string;

    /**
     * Get expression config frequency path
     */
    abstract public function getCronConfigurationFrequencyPath(): string;

    /**
     * Get expression config occurrence path
     */
    abstract public function getCronConfigurationOccurrencePath(): string;

    /**
     * Get expression config sequence path
     */
    abstract public function getCronConfigurationSequencePath(): string;

    /**
     * @var string
     */
    public const CRON_EXPRESSION_FREQUENCY_HOURLY = 'hourly';

    /** @var string  */
    public const CRON_EXPRESSION_FREQUENCY_DAILY = 'daily';

    /** @var string  */
    public const CRON_EXPRESSION_FREQUENCY_CUSTOM = 'custom';

    /** @var string  */
    public const CRON_EXPRESSION_CONFIG_GROUP_PATH = '';

    /** @var ConfigWriter */
    private $configWriter;

    /**
     * @param Context $context
     * @param ConfigWriter $configWriter
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        ConfigWriter $configWriter,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Validate cron expression before saving
     *
     * @return self
     * @throws LocalizedException
     */
    public function beforeSave(): self
    {
        $frequency = $this->getValue();
        $occurrence = $this->getOccurrence();

        if ($frequency === self::CRON_EXPRESSION_FREQUENCY_HOURLY && ($occurrence < 1 || $occurrence > 23)) {
            throw new LocalizedException(__('%1 is not a valid hourly occurrence', $occurrence));
        }

        if ($frequency === self::CRON_EXPRESSION_FREQUENCY_DAILY && ($occurrence < 1 || $occurrence > 31)) {
            throw new LocalizedException(__('%1 is not a valid daily occurrence', $occurrence));
        }

        return parent::beforeSave();
    }

    /**
     * Convert to cron expression
     *
     * @return self
     * @throws LocalizedException
     */
    public function afterSave(): self
    {
        if (empty($this->getCronExpressionPath()) || empty($this->getCronConfigurationGroupPath())) {
            throw new LocalizedException(__('Could not save the Cron expression.'));
        }

        $frequency = $this->getValue();
        $occurrence = $this->getOccurrence();
        $sequence = $this->getSequence();
        $cronExpression = '';

        if ($frequency === self::CRON_EXPRESSION_FREQUENCY_HOURLY) {
            $cronExpression = "0 */$occurrence * * *";
        }

        if ($frequency === self::CRON_EXPRESSION_FREQUENCY_DAILY) {
            $cronExpression = "0 0 */$occurrence * *";
        }

        if ($frequency === self::CRON_EXPRESSION_FREQUENCY_CUSTOM) {
            $cronExpression = $sequence;
        }

        try {
            $this->configWriter->save(
                $this->getCronExpressionPath(),
                $cronExpression,
                $this->getScope(),
                $this->getScopeId()
            );
        } catch (Exception $exception) {
            throw new LocalizedException(__('Could not save the Cron expression.'), $exception);
        }

        return parent::afterSave();
    }

    /**
     * Get occurrences
     *
     * @return int
     */
    private function getOccurrence(): int
    {
        if ($this->getFieldsetDataValue('occurrence') !== null) {
            return (int)$this->getFieldsetDataValue('occurrence');
        }

        return (int)(
            $this ->_config->getValue(
                $this->getCronConfigurationGroupPath() . '/' .
                $this->getCronConfigurationOccurrencePath()
            ) ?? 1
        );
    }

    /**
     * Get sequence
     *
     * @return string
     */
    private function getSequence(): string
    {
        if ($this->getFieldsetDataValue('sequence') !== null) {
            return $this->getFieldsetDataValue('sequence');
        }

        return (string)(
            $this ->_config->getValue(
                $this->getCronConfigurationGroupPath() . '/' . $this->getCronConfigurationSequencePath()
            ) ?? '* * * * *'
        );
    }
}
