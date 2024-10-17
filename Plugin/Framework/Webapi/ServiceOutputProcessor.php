<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Plugin\Framework\Webapi;

use Magento\Framework\Api\AbstractExtensibleObject;
use Magento\Framework\Api\AttributeInterface;
use Magento\Customer\Api\Data\CustomerInterface as CustomerDataInterface;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use Wubinworks\DisableCustomer\Helper\System as SystemHelper;
use Wubinworks\DisableCustomer\Helper\Customer as CustomerHelper;
use Wubinworks\DisableCustomer\Setup\Patch\Data\AddCustomerAttributes;

/**
 * Hide backend only attributes from API output in Customer User Context
 */
class ServiceOutputProcessor
{
    /**
     * @var SystemHelper
     */
    protected $systemHelper;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * Constructor
     *
     * @param SystemHelper $systemHelper
     * @param CustomerHelper $customerHelper
     */
    public function __construct(
        SystemHelper $systemHelper,
        CustomerHelper $customerHelper
    ) {
        $this->systemHelper = $systemHelper;
        $this->customerHelper = $customerHelper;
    }

    /**
     * Strip custom attributes for customer user context
     *
     * @param \Magento\Framework\Webapi\ServiceOutputProcessor $subject
     * @param array|object $result
     * @param mixed $data
     * @param string $type
     * @return array|object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterConvertValue(
        \Magento\Framework\Webapi\ServiceOutputProcessor $subject,
        $result,
        $data,
        $type
    ) {
        if (!$this->systemHelper->isAdminOrIntegration()
                && $this->hasCustomerData($data)
                && is_array($result)) {
            $this->traverseOnKey(
                $result,
                AbstractExtensibleObject::CUSTOM_ATTRIBUTES_KEY,
                'stripCustomAttributes'
            );
        }

        return $result;
    }

    /**
     * Verify data composition
     *
     * @param mixed $data
     * @return bool
     */
    protected function hasCustomerData($data): bool
    {
        if (($data instanceof CustomerDataInterface)
                || ($data instanceof CustomerSearchResultsInterface)) {
            return true;
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->hasCustomerData($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Traverse array and find key
     *
     * @param array $arr
     * @param string|int $key
     * @param string $method
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function traverseOnKey(array &$arr, $key, string $method)
    {
        foreach ($arr as $k => $v) {
            if ($k == $key) {
                $arr[$k] = $this->$method($arr[$k]);
            } elseif (is_array($arr[$k])) {
                $this->traverseOnKey($arr[$k], $key, $method);
            }
        }
    }

    /**
     * Strip custom attributes
     *
     * @param array|mixed $attributes
     * @return array|mixed
     */
    protected function stripCustomAttributes($attributes)
    {
        if (!is_array($attributes)) {
            return $attributes;
        }

        $result = [];
        foreach ($attributes as $attribute) {
            if (!isset($attribute[AttributeInterface::ATTRIBUTE_CODE])
                || !in_array(
                    $attribute[AttributeInterface::ATTRIBUTE_CODE],
                    $this->customerHelper->getBackendOnlyAttributeCodes()
                )) {
                $result[] = $attribute;
            }
        }

        return $result;
    }
}
