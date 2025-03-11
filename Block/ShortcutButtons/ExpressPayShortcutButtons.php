<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\ShortcutButtons;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\View\Element\Template;

class ExpressPayShortcutButtons extends Template implements ShortcutInterface
{
    public const BLOCK_ALIAS = 'shortcut.buttons.express_pay';

    protected $_template = 'Bold_CheckoutPaymentBooster::express-pay.phtml';

    public function getAlias(): string
    {
        return self::BLOCK_ALIAS;
    }
}
