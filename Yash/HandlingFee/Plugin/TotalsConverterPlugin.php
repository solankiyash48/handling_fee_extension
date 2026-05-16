<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Plugin;

use Magento\Quote\Api\Data\TotalSegmentExtensionFactory;
use Magento\Quote\Model\Cart\TotalsConverter;
use Yash\HandlingFee\Model\Quote\Total\HandlingFee;

class TotalsConverterPlugin
{
    public function __construct(
        private readonly TotalSegmentExtensionFactory $extensionFactory
    ) {
    }

    /**
     * @param TotalsConverter $subject
     * @param \Magento\Quote\Api\Data\TotalSegmentInterface[] $result
     * @return \Magento\Quote\Api\Data\TotalSegmentInterface[]
     */
    public function afterProcess(
        TotalsConverter $subject,
        array $result,
        array $addressTotals = []
    ): array {
        if (!isset($result[HandlingFee::CODE])) {
            return $result;
        }

        $segment = $result[HandlingFee::CODE];

        $extension = $segment->getExtensionAttributes() ?? $this->extensionFactory->create();
        $extension->setHandlingFee((float) $segment->getValue());

        // Pull base value from the underlying address totals if available.
        if (isset($addressTotals[HandlingFee::CODE])) {
            $extension->setBaseHandlingFee((float) $addressTotals[HandlingFee::CODE]->getBaseHandlingFee());
        }

        $segment->setExtensionAttributes($extension);
        return $result;
    }
}
