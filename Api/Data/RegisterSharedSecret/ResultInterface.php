<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\RegisterSharedSecret;

use Bold\CheckoutPaymentBooster\Api\Data\RegisterSharedSecret\ResultExtensionInterface;
use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Shared secret result data interface.
 */
interface ResultInterface extends ExtensibleDataInterface
{
    /**
     * Retrieve shop id shared secret belongs to.
     *
     * @return string|null
     */
    public function getShopId(): ?string;

    /**
     * Retrieve website code shared secret belongs to.
     *
     * @return string|null
     */
    public function getWebsiteCode(): ?string;

    /**
     * Retrieve website id shared secret belongs to.
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * Retrieve errors.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\Response\ErrorInterface[]
     */
    public function getErrors(): array;

    /**
     * Retrieve result extension attributes.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\RegisterSharedSecret\ResultExtensionInterface|null
     */
    public function getExtensionAttributes(): ?ResultExtensionInterface;
}
