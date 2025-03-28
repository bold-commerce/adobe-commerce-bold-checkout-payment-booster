<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Command;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\Client\Curl;
use Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Perform and log delete request command.
 */
class DeleteCommand
{
    /**
     * @var ResultInterfaceFactory
     */
    private $responseFactory;

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
     * @param ResultInterfaceFactory $resultFactory
     * @param Curl $client
     * @param Json $json
     * @param RequestsLogger $logger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Curl $client,
        Json $json,
        RequestsLogger $logger
    ) {
        $this->responseFactory = $resultFactory;
        $this->client = $client;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * Perform and log delete request.
     *
     * @param int $websiteId
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed[] $data
     * @return ResultInterface
     */
    public function execute(int $websiteId, string $url, array $headers, array $data): ResultInterface
    {
        $this->logger->logRequest($websiteId, $url, 'DELETE', $data);
        $this->client->setHeaders($headers);
        $this->client->delete($url, (string)$this->json->serialize($data));
        $this->logger->logResponse($websiteId, $this->client);
        return $this->responseFactory->create(
            [
                'client' => $this->client,
            ]
        );
    }
}
