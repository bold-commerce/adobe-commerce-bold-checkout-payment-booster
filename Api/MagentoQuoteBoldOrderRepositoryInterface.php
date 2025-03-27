<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface MagentoQuoteBoldOrderRepositoryInterface
{
    /**
     * @param int|string $id
     * @return MagentoQuoteBoldOrderInterface
     */
    public function get($id): MagentoQuoteBoldOrderInterface;

    /**
     * @param int|string $quoteId
     * @return MagentoQuoteBoldOrderInterface
     * @throws NoSuchEntityException
     */
    public function getByQuoteId($quoteId): MagentoQuoteBoldOrderInterface;

    /**
     * @param string $boldOrderId
     * @return MagentoQuoteBoldOrderInterface
     * @throws NoSuchEntityException
     */
    public function getByBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface;

    /**
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     */
    public function save(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void;

    /**
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder
     * @return void
     * @throws CouldNotDeleteException
     */
    public function delete(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void;

    /**
     * @param int|string $magentoQuoteBoldOrderId
     * @return void
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById($magentoQuoteBoldOrderId): void;
}
