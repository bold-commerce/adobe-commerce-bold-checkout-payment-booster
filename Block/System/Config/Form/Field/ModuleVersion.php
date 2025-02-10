<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Form\Field;

use Bold\CheckoutPaymentBooster\Block\System\Config\Form\Field;
use Bold\CheckoutPaymentBooster\Model\Modules\GetModulesInfo\GetModuleInfo;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Composer package version field in Stores Configuration.
 */
class ModuleVersion extends Field
{
    /**
     * @inheritDoc
     */
    protected $unsetScope = true;

    /**
     * @var GetModuleInfo
     */
    private $getModuleInfo;

    /**
     * @param Context $context
     * @param GetModuleInfo $getModuleInfo
     * @param array $data
     */
    public function __construct(
        Context $context,
        GetModuleInfo $getModuleInfo,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->getModuleInfo = $getModuleInfo;
    }

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element): string
    {
        try {
            $boldCheckoutPaymentBoosterInfo = $this->getModuleInfo->getInfo('Bold_CheckoutPaymentBooster');
        } catch (Exception $e) {
            return 'n/a';
        }
        $element->setLabel(__('v%1 (composer)', $boldCheckoutPaymentBoosterInfo->getVersion()));
        $element->setHtmlId('bold_checkout_payment_booster_module_version');
        $element->setText('');
        return parent::render($element);
    }
}
