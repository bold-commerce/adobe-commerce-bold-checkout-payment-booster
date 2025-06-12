<?php

namespace Bold\CheckoutPaymentBooster\Model\Data;

use Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface;
use Magento\Framework\DataObject;

class AiChatResponse extends DataObject implements AiChatResponseInterface
{
    private const SUCCESS = 'success';
    private const MESSAGE = 'message';
    private const SOURCE = 'source';
    private const CONTEXT = 'context';

    /**
     * Get success status
     *
     * @return bool
     */
    public function getSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return (string) $this->getData(self::MESSAGE);
    }

    /**
     * Set message
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * Get source
     *
     * @return string
     */
    public function getSource(): string
    {
        return (string) $this->getData(self::SOURCE);
    }

    /**
     * Set source
     *
     * @param string $source
     * @return $this
     */
    public function setSource(string $source): self
    {
        return $this->setData(self::SOURCE, $source);
    }

    /**
     * Get context
     *
     * @return array|null
     */
    public function getContext(): ?array
    {
        return $this->getData(self::CONTEXT);
    }

    /**
     * Set context
     *
     * @param array|null $context
     * @return $this
     */
    public function setContext(?array $context): self
    {
        return $this->setData(self::CONTEXT, $context);
    }
} 