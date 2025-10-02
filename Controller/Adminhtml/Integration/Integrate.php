<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Adminhtml\Integration;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\GenerateSharedSecret;
use Bold\CheckoutPaymentBooster\Model\Integration\IntegrateBoldCheckout;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles the shared secret key exchange with Bold Checkout for API Integration
 */
class Integrate extends Action
{
    /** @var ManagerInterface  */
    protected $messageManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GenerateSharedSecret
     */
    private $generateSharedSecret;

    /**
     * @var IntegrateBoldCheckout
     */
    private $integrateBoldCheckout;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor method for initializing dependencies.
     *
     * @param Context $context The application context instance.
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param GenerateSharedSecret $generateSharedSecret
     * @param IntegrateBoldCheckout $integrateBoldCheckout
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        Context $context,
        Config $config,
        StoreManagerInterface $storeManager,
        GenerateSharedSecret $generateSharedSecret,
        IntegrateBoldCheckout $integrateBoldCheckout,
        TypeListInterface $cacheTypeList,
        LoggerInterface       $logger
    ) {
        parent::__construct($context);
        $this->messageManager = $context->getMessageManager();
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->generateSharedSecret = $generateSharedSecret;
        $this->integrateBoldCheckout = $integrateBoldCheckout;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * Executes the action to integrate with Bold Checkout.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $this->logger->info('Calling integrate Bold Checkout APIs'); // TODO: Change to Bold Logger when helper implemented
            $websiteId = (int) $this->storeManager->getWebsite(true)->getId();
            $sharedSecret = $this->configureSharedSecret($websiteId);

            $this->integrateBoldCheckout->execute($websiteId, $sharedSecret);

            $this->config->setCheckoutApiIntegrationIsEnabled($websiteId, true);
            $this->config->setCheckoutApiIntegrationIsValidated($websiteId, true);
            $this->cacheTypeList->cleanType('config');

            $this->messageManager->addNoticeMessage('Config cache cleared.');
            $this->messageManager->addSuccessMessage('Bold Checkout API integration configured.');
        } catch (Exception $e) {
            $this->logger->error($e->getMessage()); // TODO: Change to Bold Logger when helper implemented
            $this->messageManager->addErrorMessage(sprintf('Unable to configure Bold Checkout API integration: %s', $e->getMessage()));
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }

    /**
     * Load or generate new shared secret.
     *
     * @param int $websiteId
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function configureSharedSecret(int $websiteId): string
    {
        $sharedSecret = $this->config->getSharedSecret($websiteId);
        if (!$sharedSecret) {
            $sharedSecret = $this->generateSharedSecret->execute();
            $this->config->setSharedSecret($websiteId, $sharedSecret);
        }
        return $sharedSecret;
    }
}
