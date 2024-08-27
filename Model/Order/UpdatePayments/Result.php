<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface;

/**
 * Update payment result data model.
 */
class Result implements ResultInterface
{
    /**
     * @var string
     */
    private $platformId;

    /**
     * @var string
     */
    private $platformFriendlyId;

    /**
     * @var string|null
     */
    private $platformCustomerId;

    /**
     * @param string $platformId
     * @param string $platformFriendlyId
     * @param string|null $platformCustomerId
     */
    public function __construct(
        string  $platformId,
        string  $platformFriendlyId,
        ?string $platformCustomerId
    ) {
        $this->platformId = $platformId;
        $this->platformFriendlyId = $platformFriendlyId;
        $this->platformCustomerId = $platformCustomerId;
    }

    /**
     * @inheritDoc
     */
    public function getPlatformId(): string
    {
        return $this->platformId;
    }

    /**
     * @inheritDoc
     */
    public function getPlatformFriendlyId(): string
    {
        return $this->platformFriendlyId;
    }

    /**
     * @inheritDoc
     */
    public function getPlatformCustomerId(): ?string
    {
        return $this->platformCustomerId;
    }
}
