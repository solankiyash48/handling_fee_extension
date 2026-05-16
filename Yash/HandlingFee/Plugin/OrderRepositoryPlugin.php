<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Plugin;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderRepositoryPlugin
{
    public function __construct(
        private readonly OrderExtensionFactory $extensionFactory
    ) {
    }

    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        return $this->applyExtensionAttributes($order);
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $searchResult
    ): OrderSearchResultInterface {
        foreach ($searchResult->getItems() as $order) {
            $this->applyExtensionAttributes($order);
        }
        return $searchResult;
    }

    public function afterSave(
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        return $this->applyExtensionAttributes($order);
    }

    private function applyExtensionAttributes(OrderInterface $order): OrderInterface
    {
        $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();

        $extension->setHandlingFee((float) $order->getData('handling_fee'));
        $extension->setBaseHandlingFee((float) $order->getData('base_handling_fee'));

        $order->setExtensionAttributes($extension);
        return $order;
    }
}
