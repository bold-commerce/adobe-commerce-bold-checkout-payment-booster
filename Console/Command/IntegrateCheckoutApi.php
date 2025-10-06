<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Console\Command;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\GenerateSharedSecret;
use Bold\CheckoutPaymentBooster\Model\Integration\IntegrateBoldCheckout;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IntegrateCheckoutApi extends Command
{
    private const WEBSITE_ID = 'websiteId';

    /**
     * @var Config
     */
    private $config;

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

    /**
     * Constructor method for initializing dependencies.
     *
     * @param Config $config
     * @param GenerateSharedSecret $generateSharedSecret
     * @param IntegrateBoldCheckout $integrateBoldCheckout
     * @param TypeListInterface $cacheTypeList
     * @return void
     */
    public function __construct(
        Config $config,
        GenerateSharedSecret $generateSharedSecret,
        IntegrateBoldCheckout $integrateBoldCheckout,
        TypeListInterface $cacheTypeList
    ) {
        parent::__construct();
        $this->config = $config;
        $this->generateSharedSecret = $generateSharedSecret;
        $this->integrateBoldCheckout = $integrateBoldCheckout;
        $this->cacheTypeList = $cacheTypeList;
    }

    protected function configure(): void
    {
        $this->setName('bold:integrate:checkout:api');
        $this->setDescription('Enable Bold Checkout API integration for Agentic commerce.');
        $this->addOption(
            self::WEBSITE_ID,
            "w",
            InputOption::VALUE_REQUIRED,
            'Website Id'
        );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($websiteId = (int) $input->getOption(self::WEBSITE_ID)) {
            $output->writeln('<info>Provided Website id is `' . $websiteId . '`</info>');
        } else {
            $errorMessage = 'The "--websiteId" option is required.';
            $spaces = str_repeat(' ', mb_strlen($errorMessage));
            $output->writeln([
                '<error>',
                '  ' . $spaces . '  ',
                '  ' . $errorMessage . '  ',
                '  ' . $spaces . '  ',
                '</error>',
                '<info>',
                'bold:integrate:checkout:api [-w|--websiteId WEBSITEID]',
                '</info>'
            ]);
            return self::INVALID;
        }

        try {
            $output->writeln('<info>Calling integrate Bold Checkout APIs.</info>');

            $sharedSecret = $this->configureSharedSecret($websiteId);
            $this->integrateBoldCheckout->execute($websiteId, $sharedSecret);

            $this->config->setCheckoutApiIntegrationIsEnabled($websiteId, true);
            $this->config->setCheckoutApiIntegrationIsValidated($websiteId, true);
            $this->cacheTypeList->cleanType('config');

            $output->writeln([
                '',
                '<info>Bold Checkout API integration configured.</info>',
                '<comment>Config updated and cache cleared.</comment>',
                ''
            ]);
        } catch (Exception $e) {
            $errorMessage = sprintf('Unable to configure Bold Checkout API integration: %s', $e->getMessage());
            $spaces = str_repeat(' ', mb_strlen($errorMessage));
            $output->writeln([
                '<error>',
                '  ' . $spaces . '  ',
                '  ' . $errorMessage . '  ',
                '  ' . $spaces . '  ',
                '</error>',
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
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
