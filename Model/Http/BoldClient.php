<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\DeleteCommand;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\GetCommand;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\PatchCommand;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\PostCommand;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\PutCommand;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Result;
use Bold\CheckoutPaymentBooster\Model\Http\Client\UserAgent;

/**
 * Client to perform http request to Bold.
 */
class BoldClient
{
    private const BOLD_API_VERSION_DATE = '2022-10-14';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UserAgent
     */
    private $userAgent;

    /**
     * @var GetCommand
     */
    private $getCommand;

    /**
     * @var PostCommand
     */
    private $postCommand;

    /**
     * @var PatchCommand
     */
    private $patchCommand;

    /**
     * @var DeleteCommand
     */
    private $deleteCommand;

    /**
     * @var PutCommand
     */
    private $putCommand;

    /**
     * @param Config $config
     * @param UserAgent $userAgent
     * @param GetCommand $getCommand
     * @param PostCommand $postCommand
     * @param PatchCommand $patchCommand
     * @param DeleteCommand $deleteCommand
     * @param PutCommand $putCommand
     */
    public function __construct(
        Config $config,
        UserAgent $userAgent,
        GetCommand $getCommand,
        PostCommand $postCommand,
        PatchCommand $patchCommand,
        DeleteCommand $deleteCommand,
        PutCommand $putCommand
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->getCommand = $getCommand;
        $this->postCommand = $postCommand;
        $this->patchCommand = $patchCommand;
        $this->deleteCommand = $deleteCommand;
        $this->putCommand = $putCommand;
    }

    /**
     * Perform get request.
     *
     * @param int $websiteId
     * @param string $url
     * @return Result
     */
    public function get(int $websiteId, string $url): Result
    {
        $url = $this->getUrl($websiteId, $url);
        $headers = $this->getHeaders($websiteId);
        return $this->getCommand->execute($websiteId, $url, $headers);
    }

    /**
     * Perform post request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return Result
     */
    public function post(int $websiteId, string $url, array $data): Result
    {
        $url = $this->getUrl($websiteId, $url);
        $headers = $this->getHeaders($websiteId);
        return $this->postCommand->execute($websiteId, $url, $headers, $data);
    }

    /**
     * Perform put request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return Result
     */
    public function put(int $websiteId, string $url, array $data): Result
    {
        $url = $this->getUrl($websiteId, $url);
        $headers = $this->getHeaders($websiteId);
        return $this->putCommand->execute($websiteId, $url, $headers, $data);
    }

    /**
     * Perform patch request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return Result
     */
    public function patch(int $websiteId, string $url, array $data): Result
    {
        $url = $this->getUrl($websiteId, $url);
        $headers = $this->getHeaders($websiteId);
        return $this->patchCommand->execute($websiteId, $url, $headers, $data);
    }

    /**
     * Perform delete request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return Result
     */
    public function delete(int $websiteId, string $url, array $data): Result
    {
        $url = $this->getUrl($websiteId, $url);
        $headers = $this->getHeaders($websiteId);
        return $this->deleteCommand->execute($websiteId, $url, $headers, $data);
    }

    /**
     * Get request headers.
     *
     * @param int $websiteId
     * @return array
     */
    private function getHeaders(int $websiteId): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config->getApiToken($websiteId),
            'Content-Type' => 'application/json',
            'User-Agent' => $this->userAgent->getUserAgentData(),
            'Bold-API-Version-Date' => self::BOLD_API_VERSION_DATE,
        ];
    }

    /**
     * Get request url.
     *
     * @param int $websiteId
     * @param string $url
     * @return string
     */
    private function getUrl(int $websiteId, string $url): string
    {
        $apiUrl = $this->config->getApiUrl($websiteId);

        if (strpos($apiUrl, 'bold.ninja') !== false) {
            $parseApiUrl = parse_url($apiUrl);
            $scheme = $parseApiUrl['scheme'];
            $host = $parseApiUrl['host'];
            $path = $parseApiUrl['path'];
            $tunnelDomain = ltrim($path, '/');
            $baseApiUrl = $scheme . '://' . $host . '/';

            if ($url === 'shops/v1/info') {
                $apiUrl = $baseApiUrl;
            }

            if (strpos($url, 'checkout_sidekick') !== false) {
                $apiUrl = $baseApiUrl . 'sidekick-' . $tunnelDomain;
            }
        }

        if (!$this->config->getShopId($websiteId)) {
            return $apiUrl . $url;
        }

        return $apiUrl . str_replace('{{shopId}}', $this->config->getShopId($websiteId), $url);
    }
}