<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Plugin\Customer\Api;

use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface as CustomerDataInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Wubinworks\DisableCustomer\Helper\System as SystemHelper;
use Wubinworks\DisableCustomer\Helper\Customer as CustomerHelper;
use Wubinworks\DisableCustomer\Setup\Patch\Data\AddCustomerAttributes;

/**
 * CustomerRepositoryInterface plugin
 */
class CustomerRepository
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
     * @var string[]
     */
    protected $attributeTypes = [
        AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED => 'bool'
    ];

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
     * Regulate data type
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerDataInterface $result
     * @param int $customerId
     * @return CustomerDataInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetById(
        CustomerRepositoryInterface $subject,
        CustomerDataInterface $result,
        $customerId
    ): CustomerDataInterface {
        $this->regulateCustomAttributeType(
            $result,
            $this->attributeTypes
        );

        return $result;
    }

    /**
     * Regulate data tyoe
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerDataInterface $result
     * @param string $email
     * @param int|null $websiteId
     * @return CustomerDataInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        CustomerRepositoryInterface $subject,
        CustomerDataInterface $result,
        $email,
        $websiteId = null
    ): CustomerDataInterface {
        $this->regulateCustomAttributeType(
            $result,
            $this->attributeTypes
        );

        return $result;
    }

    /**
     * Regulate data type
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerSearchResultsInterface $result
     * @param SearchCriteriaInterface $searchCriteria
     * @return CustomerSearchResultsInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        CustomerRepositoryInterface $subject,
        CustomerSearchResultsInterface $result,
        SearchCriteriaInterface $searchCriteria
    ): CustomerSearchResultsInterface {
        foreach ($result->getItems() as $item) {
            $this->regulateCustomAttributeType(
                $item,
                $this->attributeTypes
            );
        }

        return $result;
    }

    /**
     * Regulate custom attribute type
     *
     * @param CustomAttributesDataInterface $customAttributeData
     * @param string[] $types
     * @return void
     */
    protected function regulateCustomAttributeType(
        CustomAttributesDataInterface $customAttributeData,
        array $types
    ): void {
        foreach ($types as $code => $type) {
            $attribute = $customAttributeData->getCustomAttribute($code);
            if ($attribute) {
                $value = $attribute->getValue();
                settype($value, $type);
                $attribute->setValue($value);
            }
        }
    }

    /**
     * Prevent changing attributes in Customer User Context
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerDataInterface $customer
     * @return null
     */
    public function beforeSave(
        CustomerRepositoryInterface $subject,
        CustomerDataInterface $customer
    ) {
        if (!$this->systemHelper->isAdminOrIntegration()) {
            try {
                $origCustomer = $subject->getById((int)$customer->getId());
            } catch (\Exception $e) {
                // Create account case, there's no way to remove attribute from CustomerDataInterface
                $origCustomer = null;
            }

            foreach ($this->customerHelper->getBackendOnlyAttributeCodes() as $code) {
                if ($customer->getCustomAttribute($code)) {
                    $origValue = null; // default value
                    if ($origCustomer) {
                        $origValue = $this->customerHelper->getCustomAttributeValue($origCustomer, $code);
                    }
                    $customer->setCustomAttribute($code, $origValue);
                }
            }
        }

        return null;
    }
}
