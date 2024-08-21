<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment;

use Magento\Framework\Exception\LocalizedException;

class ValidateAuthorizationResponse
{
    /**
     * TODO: rework if needed.
     *
     * @throws LocalizedException
     */
    public function validate(array $payload)
    {
        if (!isset($payload['data']['transactions'][0]['transaction_id'])) {
            throw new LocalizedException(__('Payment authorization response does not contain transaction id.'));
        }
    }
}
