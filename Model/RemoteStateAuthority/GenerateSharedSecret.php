<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

/**
 * Shared secret generating service.
 */
class GenerateSharedSecret
{
    private const SECRET_LENGTH = 32;

    /**
     * @var Random
     */
    private $random;

    /**
     * @param Random $random
     */
    public function __construct(Random $random)
    {
        $this->random = $random;
    }

    /**
     * Generate shared secret.
     *
     * @return string
     * @throws LocalizedException
     */
    public function execute(): string
    {
        return $this->random->getRandomString(self::SECRET_LENGTH);
    }
}
