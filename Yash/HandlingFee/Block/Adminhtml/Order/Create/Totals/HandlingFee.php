<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Block\Adminhtml\Order\Create\Totals;

use Magento\Backend\Block\Template;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Yash\HandlingFee\Model\Config;

class HandlingFee extends Template
{
    protected $_template = 'Yash_HandlingFee::order/create/totals/handling-fee.phtml';

    public function __construct(
        Template\Context $context,
        private readonly SessionQuote $sessionQuote,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQuote(): \Magento\Quote\Model\Quote
    {
        return $this->sessionQuote->getQuote();
    }

    public function getHandlingFee(): float
    {
        $address = $this->getQuote()->isVirtual()
            ? $this->getQuote()->getBillingAddress()
            : $this->getQuote()->getShippingAddress();
        return (float) $address->getHandlingFee();
    }

    public function isVisible(): bool
    {
        return $this->getHandlingFee() > 0;
    }

    public function getLabel(): string
    {
        return $this->config->getLabel((int) $this->getQuote()->getStoreId());
    }

    public function getFormattedValue(): string
    {
        return (string) $this->getQuote()->getStore()->formatPrice($this->getHandlingFee(), false);
    }
}
