<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Block\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Model\Order;
use Yash\HandlingFee\Model\Config;
class Totals extends AbstractBlock
{
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?Order
    {
        return $this->getParentBlock()->getOrder();
    }

    public function getSource(): ?Order
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals(): self
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource();

        if (!$source) {
            return $this;
        }

        $fee = (float) $source->getData('handling_fee');
        if ($fee <= 0) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'handling_fee',
            'value' => $fee,
            'base_value' => (float) $source->getData('base_handling_fee'),
            'label' => $this->config->getLabel((int) $source->getStoreId()),
        ]);

        $parent->addTotalBefore($total, 'grand_total');
        return $this;
    }
}
