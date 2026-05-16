<?php
declare(strict_types=1);

namespace Yash\HandlingFee\CustomerData;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Yash\HandlingFee\Model\Calculator;
use Yash\HandlingFee\Model\Config;

class CartPlugin
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly Calculator $calculator,
        private readonly Config $config
    ) {
    }

    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return $result;
        }

        $baseSubtotal = $this->calculator->computeLiveBaseSubtotal($quote);
        $fee = $this->calculator->calculateForSubtotal($quote, $baseSubtotal);

        $result['handling_fee'] = $fee['quote'];
        $result['base_handling_fee'] = $fee['base'];
        $result['handling_fee_label'] = $this->config->getLabel((int) $quote->getStoreId());
        $result['handling_fee_applied'] = $fee['quote'] > 0;

        return $result;
    }
}
