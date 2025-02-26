<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Booster\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\CartInterface;

use function __;

class CreatorPlugin
{
    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;
    /**
     * @var CheckoutData
     */
    private $boldCheckoutData;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    public function __construct(
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckoutData $boldCheckoutData,
        CartRepositoryInterface $cartRepository
    ) {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->boldCheckoutData = $boldCheckoutData;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Reinitialize the Bold Order data and replace the Bold order identifier if necessary
     *
     * Note: this is a separate plug-in on top of our own quote creation logic to keep its scope small.
     *
     * @param Creator $subject
     * @param int|string $storeId
     * @param ProductInterface $product
     * @param mixed[] $productRequestData
     * @return mixed[]|null
     * @see Creator::createQuote
     */
    public function beforeCreateQuote(
        Creator $subject,
        $storeId,
        ProductInterface $product,
        array $productRequestData
    ): ?array {
        $boldOrderId = $productRequestData['bold_order_id'] ?? '';

        if ($boldOrderId === '') {
            return null;
        }

        try {
            $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderRepository->getByBoldOrderId($boldOrderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        if ($magentoQuoteBoldOrder->getId() === null) {
            return null;
        }

        $this->boldCheckoutData->resetCheckoutData();
        $this->boldCheckoutData->initCheckoutData();

        $productRequestData['bold_order_id'] = $this->boldCheckoutData->getPublicOrderId();

        return [
            'storeId' => $storeId,
            'product' => $product,
            'productRequestData' => $productRequestData,
        ];
    }
}
