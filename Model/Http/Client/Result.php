<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultExtensionInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Exception;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Http client response data model.
 */
class Result implements ResultInterface
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
     * @var ResultExtensionInterface|null
     */
    private $extensionAttributes;

    /**
     * @param Json $json
     * @param ClientInterface $client
     * @param ResultExtensionInterface|null $extensionAttributes
     */
    public function __construct(
        Json $json,
        ClientInterface $client,
        ?ResultExtensionInterface $extensionAttributes = null
    ) {
        $this->client = $client;
        $this->json = $json;
        $this->extensionAttributes = $extensionAttributes;
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
     * @return string[]|array{message: string, type: string, field: string, severity: string, sub_type: string}[]
     */
    public function getErrors(): array
    {
        try {
            $body = (array)$this->json->unserialize($this->client->getBody());
        } catch (Exception $e) {
            $body = [];
        }
        return $this->getErrorsFromBody($body);
    }

    /**
     * Get response body.
     *
     * @return mixed[]
     */
    public function getBody(): array
    {
        try {
            $body = (array)$this->json->unserialize($this->client->getBody());
        } catch (Exception $e) {
            $body = [];
        }
        return $this->getErrorsFromBody($body) ? [] : $body;
    }

    /**
     * Retrieve errors from response body.
     *
     * @param mixed[] $body
     * @return string[]|array{message: string, type: string, field: string, severity: string, sub_type: string}[]
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

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?ResultExtensionInterface
    {
        return $this->extensionAttributes;
    }
}
