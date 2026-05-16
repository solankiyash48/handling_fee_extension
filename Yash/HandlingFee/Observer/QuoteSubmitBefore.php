<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class QuoteSubmitBefore implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        if (!$order || !$quote) {
            return;
        }

        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        if (!$address) {
            return;
        }

        $fee = (float) $address->getHandlingFee();
        $baseFee = (float) $address->getBaseHandlingFee();

        $order->setData('handling_fee', $fee);
        $order->setData('base_handling_fee', $baseFee);
    }
}
