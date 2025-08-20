<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\ExpressPay;

interface GetInitialConfigProviderInterface
{
    /**
     * Get initial config - config provider information
     *
     * @return mixed
     */
    public function getInitialConfig();
}
