<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Command;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger;
use Magento\Framework\HTTP\ClientInterface;

/**
 * Perform and log get request command.
 */
class GetCommand
{
    /**
     * @var ResultInterfaceFactory
     */
    private $resultFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var RequestsLogger
     */
    private $logger;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ClientInterface $client
     * @param RequestsLogger $logger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ClientInterface $client,
        RequestsLogger $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Perform and log get request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $headers
     * @return ResultInterface
     */
    public function execute(int $websiteId, string $url, array $headers): ResultInterface
    {
        $this->logger->logRequest($websiteId, $url, 'GET');
        $this->client->setHeaders($headers);
        $this->client->get($url);
        $this->logger->logResponse($websiteId, $this->client);
        return $this->resultFactory->create(
            [
                'client' => $this->client,
            ]
        );
    }
}
