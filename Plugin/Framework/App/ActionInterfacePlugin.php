<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Framework\App;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;

class ActionInterfacePlugin
{
    /** @var CustomerSession */
    private $customerSession;
    /** @var HttpContext */
    private $httpContext;

    public function __construct(CustomerSession $customerSession, HttpContext $httpContext)
    {
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
    }

    public function beforeExecute(ActionInterface $subject): void
    {
        $this->httpContext->setValue('customer_id', $this->customerSession->getCustomerId(), null);
    }
}
