<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface;
use Magento\Framework\DataObject;

/**
 * Error data model.
 */
class ErrorData extends DataObject implements ErrorDataInterface
{
    /**
     * @return string;
     */
    public function getMessage(): string
    {
        return $this->getData(self::MESSAGE);
    }

    /**
     * @param string $message
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface
     */
    public function setMessage(string $message): ErrorDataInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }
}
