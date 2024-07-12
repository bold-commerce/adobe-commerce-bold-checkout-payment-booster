<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Command;

use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\Client\Curl;
use Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Result;
use Bold\CheckoutPaymentBooster\Model\Http\Client\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Perform and log put request command.
 */
class PutCommand
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var Curl
     */
    private $client;

    /**
     * @var RequestsLogger
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ResultFactory $resultFactory
     * @param Curl $client
     * @param Json $json
     * @param RequestsLogger $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        Curl $client,
        Json $json,
        RequestsLogger $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->resultFactory = $resultFactory;
        $this->json = $json;
    }

    /**
     * Perform and log put request.
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
        $this->client->put($url, $this->json->serialize($data));
        $this->logger->logResponse($websiteId, $this->client);
        return $this->resultFactory->create(
            [
                'client' => $this->client,
            ]
        );
    }
}
