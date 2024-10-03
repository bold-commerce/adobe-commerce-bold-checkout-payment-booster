<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Eps;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;

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
     * @throws \Exception
     */
    public function addDomain(int $websiteId, string $magentoUrl)
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
