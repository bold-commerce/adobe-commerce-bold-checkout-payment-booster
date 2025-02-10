<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Modules\GetModulesInfo\Result;

use Bold\CheckoutPaymentBooster\Api\Data\Module\InfoInterface;

/**
 * @inheritDoc
 */
class Info implements InfoInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name, string $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return $this->version;
    }
}
