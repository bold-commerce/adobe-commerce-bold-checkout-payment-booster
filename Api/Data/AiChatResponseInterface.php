<?php

namespace Bold\CheckoutPaymentBooster\Api\Data;

interface AiChatResponseInterface
{
    /**
     * Get success status
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set message
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

    /**
     * Get source
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Set source
     *
     * @param string $source
     * @return $this
     */
    public function setSource(string $source): self;
} 