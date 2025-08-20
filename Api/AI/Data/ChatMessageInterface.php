<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\AI\Data;

/**
 * Chat Message Interface
 */
interface ChatMessageInterface
{
    const MESSAGE = 'message';
    const ROLE = 'role';
    const TIMESTAMP = 'timestamp';
    const SESSION_ID = 'session_id';
    const PRODUCTS = 'products';
    const INTENT = 'intent';

    /**
     * Get message content
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set message content
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

    /**
     * Get message role (user, assistant, system)
     *
     * @return string
     */
    public function getRole(): string;

    /**
     * Set message role
     *
     * @param string $role
     * @return $this
     */
    public function setRole(string $role): self;

    /**
     * Get timestamp
     *
     * @return string
     */
    public function getTimestamp(): string;

    /**
     * Set timestamp
     *
     * @param string $timestamp
     * @return $this
     */
    public function setTimestamp(string $timestamp): self;

    /**
     * Get session ID
     *
     * @return string|null
     */
    public function getSessionId(): ?string;

    /**
     * Set session ID
     *
     * @param string $sessionId
     * @return $this
     */
    public function setSessionId(string $sessionId): self;

    /**
     * Get associated products
     *
     * @return array|null
     */
    public function getProducts(): ?array;

    /**
     * Set associated products
     *
     * @param array $products
     * @return $this
     */
    public function setProducts(array $products): self;

    /**
     * Get detected intent
     *
     * @return string|null
     */
    public function getIntent(): ?string;

    /**
     * Set detected intent
     *
     * @param string $intent
     * @return $this
     */
    public function setIntent(string $intent): self;
} 