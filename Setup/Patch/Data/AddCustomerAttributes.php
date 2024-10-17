<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

/**
 * Class add attributes for customer
 */
class AddCustomerAttributes implements DataPatchInterface
{
    public const CUSTOMER_ATTR_IS_DISABLED = 'ww_is_disabled';
    public const CUSTOMER_ATTR_DISABLED_CUSTOMER_MESSAGE = 'ww_disabled_customer_message';
    public const CUSTOMER_ATTR_DISABLED_AT = 'ww_disabled_at';

    public const ATTR_FRONTEND_CLASS = 'wubinworks-attribute';

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param CustomerSetupFactory $customerSetupFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $attributesInfo = [
            self::CUSTOMER_ATTR_IS_DISABLED => [
                'type' => 'int',
                'label' => 'Disabled',
                'note' => 'Disable Login, Password Reset and Email Confirmation',
                //'default' => '0',
                'visible' => false,
                'user_defined' => false,
                'system' => false,
                'input' => 'boolean',
                //'validate_rules' => '',
                'backend' => \Magento\Customer\Model\Attribute\Backend\Data\Boolean::class,
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'frontend_class' => self::ATTR_FRONTEND_CLASS . ' ' . self::CUSTOMER_ATTR_IS_DISABLED,
                'position' => 70,
                'sort_order' => 70,
                'required' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => false,
                'used_in_forms' => ['adminhtml_customer']
            ],

            self::CUSTOMER_ATTR_DISABLED_CUSTOMER_MESSAGE => [
                'type' => 'text',
                'label' => 'Disabled Customer Message',
                'note' => 'Displays when disabled customer trys to perform the above actions with correct credential',
                //'default' => '0',
                'visible' => false,
                'user_defined' => false,
                'system' => false,
                'input' => 'textarea',
                //'validate_rules' => '',
                //'backend' => '',
                //'source' => '',
                'frontend_class' => self::ATTR_FRONTEND_CLASS . ' ' . self::CUSTOMER_ATTR_DISABLED_CUSTOMER_MESSAGE,
                'position' => 71,
                'sort_order' => 71,
                'required' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
                'used_in_forms' => ['adminhtml_customer']
            ],

            self::CUSTOMER_ATTR_DISABLED_AT => [
                'type' => 'datetime',
                'label' => 'Disabled At',
                'note' => 'Timestamp for switching to disabled',
                //'default' => '0',
                'visible' => false,
                'user_defined' => false,
                'system' => false,
                'input' => 'date',
                //'validate_rules' => '',
                //'backend' => '',
                //'source' => '',
                'frontend_class' => self::ATTR_FRONTEND_CLASS . ' ' . self::CUSTOMER_ATTR_DISABLED_AT,
                'position' => 72,
                'sort_order' => 72,
                'required' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
                'used_in_forms' => false
            ]
        ];

        foreach ($attributesInfo as $attributeCode => $attributeParams) {
            $customerSetup->addAttribute(Customer::ENTITY, $attributeCode, $attributeParams);
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);
            if (array_key_exists('used_in_forms', $attributeParams) && $attributeParams['used_in_forms']) {
                $attribute->setData(
                    'used_in_forms',
                    $attributeParams['used_in_forms']
                );
            }
            $attribute->save();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * Unused
     *
     * @return string
     */
    protected static function _getVersion()
    {
        return '1.0.0';
    }
}
