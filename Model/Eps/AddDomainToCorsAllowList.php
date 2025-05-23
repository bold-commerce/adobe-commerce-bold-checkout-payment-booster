<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Eps;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;

/**
 * Add Magento domain to CORS allow list.
 */
class AddDomainToCorsAllowList
{
    private const CORS_PATH = 'checkout/shop/{{shopId}}/cors';

    /**
     * @var BoldClient
     */
    private $boldClient;

    /**
     * @param BoldClient $boldClient
     */
    public function __construct(BoldClient $boldClient)
    {
        $this->boldClient = $boldClient;
    }

    /**
     * Add Magento domain to the EPS CORS allow list.
     *
     * @param int $websiteId
     * @param string $magentoUrl
     * @return void
     * @throws \Exception
     */
    public function addDomain(int $websiteId, string $magentoUrl): void
    {
        $domainList = $this->boldClient->get($websiteId, self::CORS_PATH)->getBody();
        $domainList = $domainList['data'] ?? [];
        foreach ($domainList as $domain) {
            if ($domain['domain'] === rtrim($magentoUrl, '/')) {
                return;
            }
        }
        $response = $this->boldClient->post(
            $websiteId,
            self::CORS_PATH,
            ['domain' => rtrim($magentoUrl, '/')]
        );
        if (isset($response->error)) {
            throw new \Exception($response->error->message);
        }
    }
}
