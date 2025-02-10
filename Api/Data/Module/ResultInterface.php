<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Module;

/**
 * Get installed Bold modules information endpoint result interface.
 */
interface ResultInterface
{
    /**
     * Get installed Bold modules info.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Module\InfoInterface[]
     */
    public function getModules(): array;
}
