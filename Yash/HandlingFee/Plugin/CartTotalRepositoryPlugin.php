<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Plugin;

use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsInterface;

class CartTotalRepositoryPlugin
{
    public function __construct(
        private readonly TotalsExtensionFactory $extensionFactory
    ) {
    }

    public function afterGet(
        CartTotalRepositoryInterface $subject,
        TotalsInterface $totals
    ): TotalsInterface {
        $extension = $totals->getExtensionAttributes() ?? $this->extensionFactory->create();

        $extension->setHandlingFee((float) $totals->getData('handling_fee'));
        $extension->setBaseHandlingFee((float) $totals->getData('base_handling_fee'));

        $totals->setExtensionAttributes($extension);
        return $totals;
    }
}
