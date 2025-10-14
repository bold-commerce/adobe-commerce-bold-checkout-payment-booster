<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\ValidateApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;

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
     * @var ValidateResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var ValidateDataInterfaceFactory
     */
    private $validateDataFactory;

    /**
     * @param ValidateResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param ValidateDataInterfaceFactory $validateDataFactory
     */
    public function __construct(
        ValidateResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        ValidateDataInterfaceFactory $validateDataFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->validateDataFactory = $validateDataFactory;
    }

    /**
     * @inheritDoc
     */
    public function validate(
        string $shopId
    ): ValidateResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        $validationDataObject = $this->validateDataFactory->create();
        $validationDataObject->setValidation('success');

        return $result->setResponseHttpStatus(200)->setData($validationDataObject);
    }
}
