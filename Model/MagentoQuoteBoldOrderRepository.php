<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as ResourceModel;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

use function __;

class MagentoQuoteBoldOrderRepository implements MagentoQuoteBoldOrderRepositoryInterface
{
    /**
     * @var MagentoQuoteBoldOrderInterfaceFactory
     */
    private $magentoQuoteBoldOrderFactory;
    /**
     * @var ResourceModel
     */
    private $resourceModel;

    public function __construct(
        MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory,
        ResourceModel $resourceModel
    ) {
        $this->magentoQuoteBoldOrderFactory = $magentoQuoteBoldOrderFactory;
        $this->resourceModel = $resourceModel;
    }

    public function get($id): MagentoQuoteBoldOrderInterface
    {
        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderFactory->create();

        $this->resourceModel->load($magentoQuoteBoldOrder, $id);

        if ($magentoQuoteBoldOrder->getId() === null) {
            throw new NoSuchEntityException(
                __('Magento Quote to Bold Order link with record identifier "%1" does not exist.', $id)
            );
        }

        return $magentoQuoteBoldOrder;
    }

    public function getByQuoteId($quoteId): MagentoQuoteBoldOrderInterface
    {
        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderFactory->create();

        $this->resourceModel->load($magentoQuoteBoldOrder, $quoteId, 'quote_id');

        if ($magentoQuoteBoldOrder->getId() === null) {
            throw new NoSuchEntityException(
                __('Magento Quote to Bold Order link with quote identifier "%1" does not exist.', $quoteId)
            );
        }

        return $magentoQuoteBoldOrder;
    }

    public function getByBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface
    {
        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderFactory->create();

        $this->resourceModel->load($magentoQuoteBoldOrder, $boldOrderId, 'bold_order_id');

        if ($magentoQuoteBoldOrder->getId() === null) {
            throw new NoSuchEntityException(
                __('Magento Quote to Bold Order link with Bold order identifier "%1" does not exist.', $boldOrderId)
            );
        }

        return $magentoQuoteBoldOrder;
    }

    public function save(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void
    {
        try {
            $this->resourceModel->save($magentoQuoteBoldOrder);
        } catch (AlreadyExistsException $alreadyExistsException) {
            throw new AlreadyExistsException(
                __(
                    'Magento Quote to Bold Order link with record identifier "%1" already exists.',
                    $magentoQuoteBoldOrder->getId()
                )
            );
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__('Magento Quote to Bold Order link could not be saved.'), $exception);
        }
    }

    public function delete(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void
    {
        try {
            $this->resourceModel->delete($magentoQuoteBoldOrder);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__('Magento Quote to Bold Order link could not be deleted.'), $exception);
        }
    }

    public function deleteById($magentoQuoteBoldOrderId): void
    {
        $magentoQuoteBoldOrder = $this->get($magentoQuoteBoldOrderId);

        $this->delete($magentoQuoteBoldOrder);
    }
}
