<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\ReCaptcha\Model;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Magento\ReCaptchaCheckout\Model\WebapiConfigProvider;
use Psr\Log\LoggerInterface;

class DisableRecaptchaForExpressPayPlugin
{
    private const PLACE_ORDER_CAPTCHA_ID = 'place_order';

    /**
     * @var IsCaptchaEnabledInterface $isEnabled
     */
    private $isEnabled;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @param IsCaptchaEnabledInterface $isEnabled
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface $logger
     */
    public function __construct(
        IsCaptchaEnabledInterface $isEnabled,
        DataPersistorInterface $dataPersistor,
        LoggerInterface $logger,
    ) {
        $this->isEnabled = $isEnabled;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
    }

    /**
     * @param WebapiConfigProvider $subject
     * @param \Closure $proceed
     * @param EndpointInterface $endpoint
     * @return ValidationConfigInterface|null
     * @throws \Magento\Framework\Exception\InputException
     */
    public function aroundGetConfigFor(WebapiConfigProvider $subject, \Closure $proceed, EndpointInterface $endpoint): ?ValidationConfigInterface
    {
        if ($endpoint->getServiceMethod() === 'savePaymentInformationAndPlaceOrder'
            || $endpoint->getServiceClass() === 'Magento\QuoteGraphQl\Model\Resolver\SetPaymentAndPlaceOrder'
            || $endpoint->getServiceClass() === 'Magento\QuoteGraphQl\Model\Resolver\PlaceOrder'
        ) {
            if ($this->isEnabled->isCaptchaEnabledFor(self::PLACE_ORDER_CAPTCHA_ID)) {
                $skipRecaptcha = $this->dataPersistor->get('skip_recaptcha');
                $this->dataPersistor->clear('skip_recaptcha');
                if ($skipRecaptcha) {
                    return null;
                }
            }
        }

        return $proceed($endpoint);
    }
}
