<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\ValidateApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Magento\Framework\Exception\AuthorizationException;

class ValidateApi implements ValidateApiInterface
{
    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;
    /**
     * @var ResultInterfaceFactory
     */
    private $responseFactory;

    /**
     * @param ResultInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     */
    public function __construct(
        ResultInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
    }

    /**
     * @inheritDoc
     */
    public function validate(
        string $shopId
    ): ResultInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        // Do not remove this check until resource authorized by ACL.
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId)) {
            // Shared secret authorization failed.
            throw new AuthorizationException(__('The consumer isn\'t authorized to access resource.'));
        }

        return $this->responseFactory->create(['validation' => 'success']);
    }
}
