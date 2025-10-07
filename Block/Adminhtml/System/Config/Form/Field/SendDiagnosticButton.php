<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

/**
 * Send Diagnostic Button Block
 */
class SendDiagnosticButton extends Field
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param Context $context
     * @param UrlInterface $urlBuilder
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $data);
    }

    /**
     * Render send diagnostic button
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $buttonId = 'send_diagnostic_button';
        $ajaxUrl = $this->urlBuilder->getUrl('bold_booster/diagnostic/send');
        
        $html = '<div class="diagnostic-send-button">';
        $html .= '<button type="button" id="' . $buttonId . '" class="action-primary diagnostic-send-button__button">';
        $html .= '<span>Send Diagnostic Data to Bold</span>';
        $html .= '</button>';
        $html .= '<span id="diagnostic_status" class="diagnostic-send-button__status-indicator"></span>';
        $html .= '</div>';

        $html .= '<script type="text/javascript">
            require([
                "Bold_CheckoutPaymentBooster/js/send-diagnostic"
            ], function (sendDiagnostic) {
                sendDiagnostic({
                    buttonId: "' . $buttonId . '",
                    ajaxUrl: "' . $ajaxUrl . '"
                });
            });
        </script>';

        return $html;
    }
}
