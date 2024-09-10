<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Http;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;

/**
 * HTTP client interface to make requests to Bold side|Platform.
 */
interface ClientInterface
{
    /**
     * Perform GET HTTP request to Bold|platform.
     *
     * @param int $websiteId
     * @param string $url
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     * @throws \Exception
     */
    public function get(int $websiteId, string $url): ResultInterface;

    /**
     * Perform POST HTTP request to Bold|platform.
     *
     * @param int $websiteId
     * @param string $url
     * @param array|null $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     * @throws \Exception
     */
    public function post(int $websiteId, string $url, array $data): ResultInterface;

    /**
     * Perform PUT HTTP request to Bold|platform.
     *
     * @param int $websiteId
     * @param string $url
     * @param array|null $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     * @throws \Exception
     */
    public function put(int $websiteId, string $url, array $data): ResultInterface;

    /**
     * Perform PATCH HTTP request to Bold|platform.
     *
     * @param int $websiteId
     * @param string $url
     * @param array|null $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     * @throws \Exception
     */
    public function patch(int $websiteId, string $url, array $data): ResultInterface;

    /**
     * Perform DELETE HTTP request to Bold|platform.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     * @throws \Exception
     */
    public function delete(int $websiteId, string $url, array $data): ResultInterface;
}
