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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Class MagentoQuoteBoldOrderRepository
 *
 * Implements repository functionality for managing the relationship between Magento Quotes
 * and Bold Orders. Provides operations to retrieve, save, and delete data and to manage
 * custom logic related to quote and Bold order processing.
 */
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

    /** @var LoggerInterface */
    private $logger;

    /** @var TimezoneInterface  */
    private $timezoneInterface;

    /**
     * Constructor method.
     *
     * @param MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory Bold Quote Order interface.
     * @param ResourceModel $resourceModel Resource model instance.
     * @param LoggerInterface $logger Logger instance for logging operations.
     */
    public function __construct(
        MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory,
        ResourceModel $resourceModel,
        LoggerInterface $logger,
        TimezoneInterface $timezoneInterface
    ) {
        $this->magentoQuoteBoldOrderFactory = $magentoQuoteBoldOrderFactory;
        $this->resourceModel = $resourceModel;
        $this->logger = $logger;
        $this->timezoneInterface = $timezoneInterface;
    }

    /**
     * Retrieve Magento Quote to Bold Order link by its identifier.
     *
     * @param int|string $id The identifier of the Magento Quote to Bold Order link.
     * @return MagentoQuoteBoldOrderInterface The corresponding Magento Quote to Bold Order object.
     * @throws NoSuchEntityException If the entity with the specified identifier does not exist.
     */
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

    /**
     * Retrieve Magento Quote to Bold Order link by its quote identifier.
     *
     * @param int|string $quoteId The quote identifier of the Magento Quote to Bold Order link.
     * @return MagentoQuoteBoldOrderInterface The corresponding Magento Quote to Bold Order object.
     * @throws NoSuchEntityException If the entity with the specified quote identifier does not exist.
     */
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

    /**
     * Retrieve Magento Quote to Bold Order link by its Bold order identifier.
     *
     * @param string $boldOrderId The Bold order identifier of the Magento Quote to Bold Order link.
     * @return MagentoQuoteBoldOrderInterface The corresponding Magento Quote to Bold Order object.
     * @throws NoSuchEntityException If the entity with the specified Bold order identifier does not exist.
     */
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

    /**
     * Save the Magento Quote to Bold Order link.
     *
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder The Magento Quote to Bold Order object to save.
     * @return void
     * @throws AlreadyExistsException If the entity with the specified identifier already exists.
     * @throws CouldNotSaveException If the entity could not be saved due to an error.
     */
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

    /**
     * Delete the specified Magento Quote to Bold Order link.
     *
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder The Magento Quote to Bold Order object to delete.
     * @return void
     * @throws CouldNotDeleteException If the entity could not be deleted.
     */
    public function delete(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void
    {
        try {
            $this->resourceModel->delete($magentoQuoteBoldOrder);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__('Magento Quote to Bold Order link could not be deleted.'), $exception);
        }
    }

    /**
     * Delete the Magento Quote to Bold Order link by its identifier.
     *
     * @param int|string $magentoQuoteBoldOrderId The identifier of the Magento Quote to Bold Order link.
     * @return void
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException If the entity with the specified identifier does not exist.
     */
    public function deleteById($magentoQuoteBoldOrderId): void
    {
        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->get($magentoQuoteBoldOrderId);

        $this->delete($magentoQuoteBoldOrder);
    }

    /**
     * Find Or Create Bold Quote Public Order Relation by Quote ID
     *
     * @param string $quoteId
     * @return MagentoQuoteBoldOrderInterface
     */
    public function findOrCreateByQuoteId(string $quoteId): MagentoQuoteBoldOrderInterface
    {
        try {
            /** @var MagentoQuoteBoldOrder $relation */
            $relation = $this->getByQuoteId($quoteId);
        } catch (NoSuchEntityException $e) {
            /** @var MagentoQuoteBoldOrder $relation */
            $relation = $this->magentoQuoteBoldOrderFactory->create();
        }
        return $relation;
    }

    /**
     * Is Quote ID Processed (Has successful State call)
     *
     * @param string $quoteId
     * @return bool
     */
    public function isQuoteProcessed(string $quoteId): bool
    {
        return $this->findOrCreateByQuoteId($quoteId)->isProcessed();
    }

    /**
     * Check if the order has successful State call.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isBoldOrderProcessed(OrderInterface $order): bool
    {
        $quoteId = $order->getQuoteId();
        return $this->isQuoteProcessed((string) $quoteId);
    }

    /**
     * Save Bold Public Order ID and Quote ID
     *
     * @param string $publicOrderId
     * @param string $quoteId
     * @return void
     */
    public function saveBoldQuotePublicOrderRelation(string $publicOrderId, string $quoteId): void
    {
        try {
            /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $relation */
            $relation = $this->findOrCreateByQuoteId($quoteId);
            $relation->setQuoteId($quoteId);
            $relation->setBoldOrderId($publicOrderId);
            $this->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Save Successful Authorize Full Amount at to Bold Quote Public Order Relation
     *
     * @param string $field
     * @param string $quoteId
     * @return void
     */
    public function saveTimeStamp(string $field, string $quoteId): void
    {
        $timestamp = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        try {
            /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $relation */
            $relation = $this->findOrCreateByQuoteId($quoteId);
            $relation->setQuoteId($quoteId);
            $relation->setData($field, $timestamp);
            $this->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }


    /**
     * Retrieve the Bold Order associated with the given Magento Order.
     *
     * @param OrderInterface $order The Magento Order object.
     * @return string The corresponding Bold Order object.
     * @throws Exception If a general error occurs.
     */
    public function getPublicOrderIdFromOrder(OrderInterface $order): ?string
    {
        $quoteId = (string) $order->getQuoteId();
        if (!$quoteId) {
            return null;
        }
        $relation = $this->findOrCreateByQuoteId($quoteId);
        return $relation->getBoldOrderId();
    }

    /**
     * Saves the authorization timestamp for a given quote.
     *
     * @param string $quoteId The identifier of the quote.
     * @return void
     */
    public function saveAuthorizedAt(string $quoteId): void
    {
        $this->saveTimeStamp(
            MagentoQuoteBoldOrderInterface::SUCCESSFUL_AUTH_FULL_AT,
            $quoteId
        );
    }

    /**
     * Saves the hydrated timestamp for a given quote.
     *
     * @param string $quoteId The identifier of the quote.
     * @return void
     */
    public function saveHydratedAt(string $quoteId): void
    {
        $this->saveTimeStamp(
            MagentoQuoteBoldOrderInterface::SUCCESSFUL_HYDRATE_AT,
            $quoteId
        );
    }

    /**
     * Saves the processed timestamp for a given quote.
     *
     * @param string $quoteId The identifier of the quote.
     * @return void
     */
    public function saveStateAt(string $quoteId): void
    {
        $this->saveTimeStamp(
            MagentoQuoteBoldOrderInterface::SUCCESSFUL_STATE_AT,
            $quoteId
        );
    }
}
