<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Model;

use Magento\Quote\Model\Quote;

class Calculator
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function calculate(Quote $quote): array
    {
        return $this->calculateForSubtotal($quote, (float) $quote->getBaseSubtotal());
    }

    public function calculateForSubtotal(Quote $quote, float $baseSubtotal): array
    {
        $zero = ['base' => 0.0, 'quote' => 0.0];

        $storeId = (int) $quote->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            return $zero;
        }

        if ($baseSubtotal <= 0) {
            return $zero;
        }

        if ($baseSubtotal > $this->config->getSubtotalThreshold($storeId)) {
            return $zero;
        }

        $groupId = $quote->getCustomerGroupId();
        if ($groupId !== null && $this->config->isExemptGroup((int) $groupId, $storeId)) {
            return $zero;
        }

        $rate = $this->config->getRatePercent($storeId);
        if ($rate <= 0) {
            return $zero;
        }

        $baseFee = round($baseSubtotal * $rate / 100, 4);
        $rateToQuote = (float) ($quote->getBaseToQuoteRate() ?: 1.0);
        $quoteFee = round($baseFee * $rateToQuote, 4);

        return ['base' => $baseFee, 'quote' => $quoteFee];
    }

    public function computeLiveBaseSubtotal(Quote $quote): float
    {
        $sum = 0.0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $sum += (float) $item->getBaseRowTotal();
        }
        return $sum;
    }
}
