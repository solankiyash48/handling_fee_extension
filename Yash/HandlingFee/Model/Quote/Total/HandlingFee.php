<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Model\Quote\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Yash\HandlingFee\Model\Calculator;
use Yash\HandlingFee\Model\Config;

class HandlingFee extends AbstractTotal
{
    public const CODE = 'handling_fee';

    public function __construct(
        private readonly Calculator $calculator,
        private readonly Config $config
    ) {
        $this->setCode(self::CODE);
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $total->setHandlingFee(0);
        $total->setBaseHandlingFee(0);
        $quote->setHandlingFee(0);
        $quote->setBaseHandlingFee(0);

        $items = $shippingAssignment->getItems();
        if (empty($items)) {
            return $this;
        }

        $address = $shippingAssignment->getShipping()->getAddress();
        if (!$quote->isVirtual() && $address->getAddressType() !== \Magento\Quote\Model\Quote\Address::ADDRESS_TYPE_SHIPPING) {
            return $this;
        }

        $fee = $this->calculator->calculate($quote);

        if ($fee['base'] <= 0) {
            $address->setHandlingFee(0);
            $address->setBaseHandlingFee(0);
            return $this;
        }

        $total->setHandlingFee($fee['quote']);
        $total->setBaseHandlingFee($fee['base']);

        $address->setHandlingFee($fee['quote']);
        $address->setBaseHandlingFee($fee['base']);

        $quote->setHandlingFee($fee['quote']);
        $quote->setBaseHandlingFee($fee['base']);

        $total->setTotalAmount(self::CODE, $fee['quote']);
        $total->setBaseTotalAmount(self::CODE, $fee['base']);

        $total->setGrandTotal((float) $total->getGrandTotal() + $fee['quote']);
        $total->setBaseGrandTotal((float) $total->getBaseGrandTotal() + $fee['base']);

        return $this;
    }

    public function fetch(Quote $quote, Total $total)
    {
        $amount = (float) $total->getHandlingFee();
        if ($amount <= 0) {
            return null;
        }

        return [
            'code' => self::CODE,
            'title' => $this->config->getLabel((int) $quote->getStoreId()),
            'value' => $amount,
        ];
    }

    public function getLabel()
    {
        return $this->config->getLabel();
    }
}
