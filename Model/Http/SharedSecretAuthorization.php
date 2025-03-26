<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Authorize request by shared secret.
 */
class SharedSecretAuthorization
{
    private const MAX_TIME_DELTA = 3;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param Config $config
     * @param Request $request
     * @param DateTime $dateTime
     */
    public function __construct(
        Config   $config,
        Request  $request,
        DateTime $dateTime
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->dateTime = $dateTime;
    }

    /**
     * Authorize request by shared secret.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isAuthorized(int $websiteId): bool
    {
        $timestampHeader = (string)$this->request->getHeader('X-HMAC-Timestamp', '');
        if (!$this->validateTimestamp($timestampHeader)) {
            return false;
        }
        $sharedSecret = $this->config->getSharedSecret($websiteId);
        $signatureHeader = $this->request->getHeader('Signature');
        preg_match('/signature="(\S*?)"/', $signatureHeader, $matches);
        $signature = $matches[1] ?? null;
        if (!$signature) {
            return false;
        }
        $timestamp = sprintf('x-hmac-timestamp: %s', $timestampHeader);

        return hash_equals(
            base64_encode(
                hash_hmac(
                    'sha256',
                    $timestamp,
                    $sharedSecret,
                    true
                )
            ),
            $signature
        );
    }

    /**
     * Ensure the timestamp is not older than MAX_TIME_DELTA seconds.
     *
     * @param string $timestamp
     * @return bool
     */
    private function validateTimestamp(string $timestamp): bool
    {
        $utcTimestamp = $this->dateTime->gmtTimestamp();
        $requestTimestamp = $this->dateTime->gmtTimestamp($timestamp);

        return $utcTimestamp - $requestTimestamp <= self::MAX_TIME_DELTA;
    }
}
