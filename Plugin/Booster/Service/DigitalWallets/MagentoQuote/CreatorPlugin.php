<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Booster\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

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

    public function __construct(
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckoutData $boldCheckoutData
    ) {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->boldCheckoutData = $boldCheckoutData;
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
        /** @var string $boldOrderId */
        $boldOrderId = $productRequestData['bold_order_id'] ?? '';

        if ($boldOrderId === '') {
            return null;
        }

        try {
            $this->magentoQuoteBoldOrderRepository->getByBoldOrderId($boldOrderId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        $this->boldCheckoutData->resetCheckoutData();
        $this->boldCheckoutData->initCheckoutData();

        $productRequestData['bold_order_id'] = $this->boldCheckoutData->getPublicOrderId();

        return [
            $storeId,
            $product,
            $productRequestData,
        ];
    }
}
