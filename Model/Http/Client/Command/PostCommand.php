<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Command;

use Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Result;
use Bold\CheckoutPaymentBooster\Model\Http\Client\ResultFactory;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Perform and log post request command.
 */
class PostCommand
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var RequestsLogger
     */
    private $logger;

    /**
     * @var ResultFactory
     */
    private $responseFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ResultFactory $responseFactory
     * @param ClientInterface $client
     * @param Json $json
     * @param RequestsLogger $logger
     */
    public function __construct(
        ResultFactory $responseFactory,
        ClientInterface $client,
        Json $json,
        RequestsLogger $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->responseFactory = $responseFactory;
        $this->json = $json;
    }

    /**
     * Perform and log get request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $headers
     * @param array $data
     * @return Result
     */
    public function execute(int $websiteId, string $url, array $headers, array $data): Result
    {
        $this->logger->logRequest($websiteId, $url, 'POST', $data);
        $this->client->setHeaders($headers);
        $this->client->post($url, $this->json->serialize($data));
        $this->logger->logResponse($websiteId, $this->client);
        return $this->responseFactory->create(
            [
                'client' => $this->client,
            ]
        );
    }
}
