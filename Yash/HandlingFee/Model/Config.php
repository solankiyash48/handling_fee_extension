<?php
declare(strict_types=1);

namespace Yash\HandlingFee\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'handling_fee/general/enabled';
    public const XML_PATH_RATE = 'handling_fee/general/rate_percent';
    public const XML_PATH_THRESHOLD = 'handling_fee/general/exempt_subtotal_threshold';
    public const XML_PATH_EXEMPT_GROUPS = 'handling_fee/general/exempt_customer_groups';
    public const XML_PATH_LABEL = 'handling_fee/general/label';

    public const DEFAULT_LABEL = 'Handling Fee';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRatePercent(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_RATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSubtotalThreshold(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return int[]
     */
    public function getExemptCustomerGroupIds(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EXEMPT_GROUPS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw)), static fn($v) => $v >= 0 || $v === 0));
    }

    public function isExemptGroup(?int $customerGroupId, ?int $storeId = null): bool
    {
        if ($customerGroupId === null) {
            return false;
        }
        return in_array((int) $customerGroupId, $this->getExemptCustomerGroupIds($storeId), true);
    }

    public function getLabel(?int $storeId = null): string
    {
        $label = (string) $this->scopeConfig->getValue(
            self::XML_PATH_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $label !== '' ? $label : self::DEFAULT_LABEL;
    }
}
