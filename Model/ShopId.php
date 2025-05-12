<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Exception;

/**
 * Bold shop id management model.
 */
class ShopId
{
    private const SHOP_INFO_URL = 'shops/v1/info';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @param BoldClient $client
     */
    public function __construct(BoldClient $client)
    {
        $this->client = $client;
    }

    /**
     * Retrieve shop id from Bold.
     *
     * @param int $websiteId
     * @return string
     * @throws Exception
     */
    public function getShopId(int $websiteId): string
    {
        $shopInfo = $this->client->get($websiteId, self::SHOP_INFO_URL);
        if ($shopInfo->getErrors()) {
            $error = current($shopInfo->getErrors());
            throw new Exception($error);
        }
        return $shopInfo->getBody()['shop_identifier'];
    }
}
