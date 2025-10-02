<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\UrlInterface;

/**
 * Class IntegrateCheckoutApiButton
 *
 * Represents a button element used for exchanging integration API key with Bold Checkout and runs validation.
 * This class extends the Field class and primarily focuses on rendering
 * the button and generating the corresponding AJAX URL for the export action.
 */
class IntegrateCheckoutApiButton extends Field
{
    /** @var UrlInterface  */
    private $urlBuilder;

    /**
     * Constructor for the class, initializing context and URL builder.
     *
     * @param Context $context The context object containing resources and configurations.
     * @param array $data Optional data array for additional configuration.
     * @return void
     * @phpstan-ignore-next-line
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Generates the HTML content for the specified element.
     *
     * @param AbstractElement $element The element for which the HTML is to be generated.
     * @return string The generated HTML content.
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return sprintf(
            '
                <div style="margin-top: 5px;">
                    <button type="button" class="action-default scalable"
                        onclick="window.location.href=\'%s\'">
                        <span>%s</span>
                    </button>
                </div>
                ',
            $this->getAjaxUrl(),
            __('Integrate Bold Checkout API')
        );
    }

    /**
     * Generates the AJAX URL to trigger Bold Checkout API integration.
     *
     * @return string The AJAX URL used to trigger Bold Checkout API integration.
     */
    public function getAjaxUrl(): string
    {
        return $this->urlBuilder->getUrl('bold_booster/integration/integrate');
    }
}
