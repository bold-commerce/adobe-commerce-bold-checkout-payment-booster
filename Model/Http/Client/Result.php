<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client;

use Exception;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Http client response data model.
 */
class Result
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Json $json
     * @param ClientInterface $client
     */
    public function __construct(
        Json $json,
        ClientInterface $client
    ) {
        $this->client = $client;
        $this->json = $json;
    }

    /**
     * Get response status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->client->getStatus();
    }

    /**
     * Get errors from response body.
     *
     * @return array
     */
    public function getErrors(): array
    {
        try {
            $body = $this->json->unserialize($this->client->getBody());
        } catch (Exception $e) {
            $body = [];
        }
        return $this->getErrorsFromBody($body);
    }

    /**
     * Get response body.
     *
     * @return array
     */
    public function getBody(): array
    {
        try {
            $body = $this->json->unserialize($this->client->getBody());
        } catch (Exception $e) {
            $body = [];
        }
        return $this->getErrorsFromBody($body) ? [] : $body;
    }

    /**
     * Retrieve errors from response body.
     *
     * @param array $body
     * @return array
     */
    private function getErrorsFromBody(array $body): array
    {
        $errors = $body['errors'] ?? [];
        if (isset($body['error'])) {
            $errors = [
                $body['error_description'] ?? $body['error'],
            ];
        }
        return $errors;
    }
}
