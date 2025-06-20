<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\AI\Data\ChatMessageInterface;
use Magento\Framework\DataObject;

/**
 * Chat Message Model
 */
class ChatMessage extends DataObject implements ChatMessageInterface
{
    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return (string)$this->getData(self::MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setMessage(string $message): ChatMessageInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritDoc
     */
    public function getRole(): string
    {
        return (string)$this->getData(self::ROLE);
    }

    /**
     * @inheritDoc
     */
    public function setRole(string $role): ChatMessageInterface
    {
        return $this->setData(self::ROLE, $role);
    }

    /**
     * @inheritDoc
     */
    public function getTimestamp(): string
    {
        return (string)$this->getData(self::TIMESTAMP);
    }

    /**
     * @inheritDoc
     */
    public function setTimestamp(string $timestamp): ChatMessageInterface
    {
        return $this->setData(self::TIMESTAMP, $timestamp);
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): ?string
    {
        return $this->getData(self::SESSION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): ChatMessageInterface
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    /**
     * @inheritDoc
     */
    public function getProducts(): ?array
    {
        return $this->getData(self::PRODUCTS);
    }

    /**
     * @inheritDoc
     */
    public function setProducts(array $products): ChatMessageInterface
    {
        return $this->setData(self::PRODUCTS, $products);
    }

    /**
     * @inheritDoc
     */
    public function getIntent(): ?string
    {
        return $this->getData(self::INTENT);
    }

    /**
     * @inheritDoc
     */
    public function setIntent(string $intent): ChatMessageInterface
    {
        return $this->setData(self::INTENT, $intent);
    }
} 