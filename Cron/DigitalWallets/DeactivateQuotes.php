<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Cron\DigitalWallets;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Deactivator;

class DeactivateQuotes
{
    /**
     * @var Deactivator
     */
    private $quoteDeactivator;

    public function __construct(Deactivator $quoteDeactivator)
    {
        $this->quoteDeactivator = $quoteDeactivator;
    }

    public function execute(): void
    {
        $this->quoteDeactivator->deactivateAllQuotes();
    }
}
