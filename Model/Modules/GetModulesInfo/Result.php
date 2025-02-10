<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Modules\GetModulesInfo;

use Bold\CheckoutPaymentBooster\Api\Data\Module\InfoInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Module\ResultInterface;

/**
 * Get installed Bold modules info endpoint result model.
 */
class Result implements ResultInterface
{
    /**
     * @var InfoInterface[]
     */
    private $modules;

    /**
     * @param InfoInterface[] $modules
     */
    public function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    /**
     * @inheritDoc
     */
    public function getModules(): array
    {
        return $this->modules;
    }
}
