<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Quote;

use Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote\QuoteExtensionData as QuoteExtensionDataResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Bold quote data entity.
 */
class QuoteExtensionData extends AbstractModel
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param SerializerInterface $serializer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context             $context,
        Registry            $registry,
        SerializerInterface $serializer,
        AbstractResource    $resource = null,
        AbstractDb          $resourceCollection = null,
        array               $data = []
    )
    {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->serializer = $serializer;
    }

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(QuoteExtensionDataResource::class);
    }

    /**
     * Set quote entity id.
     *
     * @param int $quoteId
     * @return void
     */
    public function setQuoteId(int $quoteId): void
    {
        $this->setData(QuoteExtensionDataResource::QUOTE_ID, $quoteId);
    }

    /**
     * Retrieve quote id.
     *
     * @return int|null
     */
    public function getQuoteId(): ?int
    {
        return $this->getData(QuoteExtensionDataResource::QUOTE_ID)
            ? (int)$this->getData(QuoteExtensionDataResource::QUOTE_ID)
            : null;
    }

    /**
     * Set order should be created on Magento side.
     *
     * @param bool $orderCreated
     * @return void
     */
    public function setOrderCreated(bool $orderCreated): void
    {
        $this->setData(QuoteExtensionDataResource::ORDER_CREATED, $orderCreated);
    }

    /**
     * Get order should be created on Magento side.
     *
     * @return bool|null
     */
    public function getOrderCreated(): ?bool
    {
        return (bool)$this->getData(QuoteExtensionDataResource::ORDER_CREATED);
    }

    /**
     * Set Bold public order id.
     *
     * @param string $orderId
     * @return void
     */
    public function setPublicOrderId(string $orderId): void
    {
        $this->setData(QuoteExtensionDataResource::PUBLIC_ID, $orderId);
    }

    /**
     * Get Bold public order id.
     *
     * @return string|null
     */
    public function getPublicOrderId(): ?string
    {
        return $this->getData(QuoteExtensionDataResource::PUBLIC_ID);
    }

    /**
     * Set Bold flow settings.
     *
     * @param array $flowSettings
     * @return void
     */
    public function setFlowSettings(array $flowSettings): void
    {
        $serializedSettings = $this->serializer->serialize($flowSettings);

        $this->setData(QuoteExtensionDataResource::FLOW_SETTINGS, $serializedSettings);
    }

    /**
     * Get Bold flow settings.
     *
     * @return array
     */
    public function getFlowSettings(): array
    {
        $serializedSettings = $this->getData(QuoteExtensionDataResource::FLOW_SETTINGS);

        try {
            return $this->serializer->unserialize($serializedSettings);
        } catch (\Exception $exception) {
            return [];
        }
    }
}
