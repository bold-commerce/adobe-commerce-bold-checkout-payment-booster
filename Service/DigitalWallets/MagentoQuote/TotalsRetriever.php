<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\Cart\Totals\Item;
use Magento\Quote\Model\Cart\TotalSegment;

use function array_map;
use function is_object;

/**
 * @api
 */
class TotalsRetriever
{
    /**
     * @var CartTotalRepositoryInterface
     */
    private $cartTotalRepository;

    public function __construct(CartTotalRepositoryInterface $cartTotalRepository)
    {
        $this->cartTotalRepository = $cartTotalRepository;
    }

    /**
     * @param int|string $quoteId
     * @return mixed[]
     * @throws NoSuchEntityException
     * @see \Magento\Checkout\Model\DefaultConfigProvider::getTotalsData
     */
    public function retrieveTotals($quoteId): array
    {
        $totals = $this->cartTotalRepository->get((int)$quoteId);
        $items = array_map(
            static function (Item $item): array {
                return $item->__toArray();
            },
            $totals->getItems() ?? []
        );
        $totalSegmentsData = [];

        /** @var TotalSegment $totalSegment */
        foreach ($totals->getTotalSegments() as $totalSegment) {
            $totalSegmentArray = $totalSegment->toArray();

            if (is_object($totalSegment->getExtensionAttributes())) {
                $totalSegmentArray['extension_attributes'] = $totalSegment->getExtensionAttributes()->__toArray();
            }

            $totalSegmentsData[] = $totalSegmentArray;
        }

        $totals->setItems($items);
        $totals->setTotalSegments($totalSegmentsData);

        $totalsArray = $totals->toArray();

        if (is_object($totals->getExtensionAttributes())) {
            $totalsArray['extension_attributes'] = $totals->getExtensionAttributes()->__toArray();
        }

        return $totalsArray;
    }
}
