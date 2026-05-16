<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Block\Success;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Yash\HandlingFee\Model\Config;

class HandlingFee extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PricingHelper $pricingHelper,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?Order
    {
        $orderId = $this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return null;
        }
        try {
            /** @var Order $order */
            $order = $this->orderRepository->get((int) $orderId);
            return $order;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getHandlingFee(): float
    {
        $order = $this->getOrder();
        return $order ? (float) $order->getData('handling_fee') : 0.0;
    }

    public function getFormattedFee(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }
        return (string) $this->pricingHelper->currencyByStore(
            $this->getHandlingFee(),
            $order->getStoreId(),
            true,
            false
        );
    }

    public function getLabel(): string
    {
        $order = $this->getOrder();
        return $this->config->getLabel($order ? (int) $order->getStoreId() : null);
    }

    public function isVisible(): bool
    {
        return $this->getHandlingFee() > 0;
    }
}
