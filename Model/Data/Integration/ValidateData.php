<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateDataInterface;
use Magento\Framework\DataObject;

/**
 * Validation data model.
 */
class ValidateData extends DataObject implements ValidateDataInterface
{
    /**
     * @return string;
     */
    public function getValidation(): string
    {
        return $this->getData(self::VALIDATION);
    }

    /**
     * @param string $validation
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateDataInterface
     */
    public function setValidation(string $validation): ValidateDataInterface
    {
        return $this->setData(self::VALIDATION, $validation);
    }
}
