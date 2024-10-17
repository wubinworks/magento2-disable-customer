<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Helper;

use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\Api\AbstractExtensibleObject;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface as CustomerDataInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\LoginAsCustomerApi\Api\IsLoginAsCustomerEnabledForCustomerInterface;
use Magento\LoginAsCustomerApi\Api\IsLoginAsCustomerSessionActiveInterface;
use Wubinworks\DisableCustomer\Setup\Patch\Data\AddCustomerAttributes;

/**
 * Customer helper
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Customer extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * These attributes are invisible and unchangable in 'Customer User Context'. Used in plugins
     *
     * @var array
     */
    protected $backendOnlyAttributeCodes;

    /**
     * Constructor
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFactory $customerFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param string[] $backendOnlyAttributeCodes check di.xml
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Helper\Context $context,
        array $backendOnlyAttributeCodes = []
    ) {
        parent::__construct($context);
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->backendOnlyAttributeCodes = $backendOnlyAttributeCodes;
    }

    /**
     * Get custom attribute value
     *
     * @param CustomAttributesDataInterface|mixed $customAttributeData
     * @param string $code
     * @return mixed|null
     */
    public function getCustomAttributeValue($customAttributeData, string $code)
    {
        if (($customAttributeData instanceof CustomAttributesDataInterface)
            && $customAttributeData->getCustomAttribute($code)) {
            return $customAttributeData->getCustomAttribute($code)->getValue();
        }

        return null;
    }

    /**
     * Set custom attribute value
     *
     * @param CustomAttributesDataInterface $customAttributeData
     * @param string $code
     * @param mixed $value
     * @return void
     */
    public function setCustomAttributeValue(CustomAttributesDataInterface $customAttributeData, string $code, $value)
    {
        $customAttributeData->setCustomAttribute($code, $value);
    }

    /**
     * Get customer data model
     *
     * @param int|string $input
     * @return CustomerDataInterface|bool
     *
     * @throws \InvalidArgumentException
     * @throws NoSuchEntityException If customer does not exist.
     * @throws LocalizedException
     */
    public function getCustomerDataModel($input)
    {
        if (!is_int($input) && !is_string($input)) {
            throw new \InvalidArgumentException('Input type must be int or string.');
        }

        try {
            if (is_int($input) || (ctype_digit($input) && strlen($input) < 10)) {
                return $this->customerRepository->getById((int)$input);
            } else {
                return $this->customerRepository->get($input);
            }
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Check if customer is disabled
     *
     * @param int|string $input
     * @return bool
     *
     * @throws LocalizedException
     */
    public function isCustomerDisabled($input): bool
    {
        $customerData = $this->getCustomerDataModel($input);
        if ($customerData) {
            return (bool)$this->getCustomAttributeValue(
                $customerData,
                AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED
            );
        }

        return false;
    }

    /**
     * Get disabled customer message
     *
     * @param int|string $input
     * @return string
     *
     * @throws LocalizedException
     */
    public function getDisabledCustomerMessage($input): string
    {
        $customerData = $this->getCustomerDataModel($input);
        if ($customerData) {
            return (string)$this->getCustomAttributeValue(
                $customerData,
                AddCustomerAttributes::CUSTOMER_ATTR_DISABLED_CUSTOMER_MESSAGE
            );
        }

        return '';
    }

    /**
     * Get customer disabled time
     *
     * @param int|string $input
     * @return string|null
     *
     * @throws LocalizedException
     */
    public function getCustomerDisabledAt($input): ?string
    {
        $customerData = $this->getCustomerDataModel($input);
        if ($customerData) {
            $value = $this->getCustomAttributeValue(
                $customerData,
                AddCustomerAttributes::CUSTOMER_ATTR_DISABLED_AT
            );
            // Check empty string
            if ($value) {
                return (string)$value;
            }
        }

        return null;
    }

    /**
     * Update customer attribute directly. You can update `backend only attributes` in `Customer User Context`
     *
     * @param int|string $input customer Id or email
     * @param string $code attribute code
     * @param mixed $value attribute value
     * @return bool false if failed
     */
    public function updateCustomerAttribute($input, string $code, $value): bool
    {
        try {
            /** @var CustomerModel $customerModel */
            $customerModel = $this->customerFactory->create();
            if (is_int($input) || (ctype_digit($input) && strlen($input) < 10)) {
                $customerModel->load((int)$input);
            } else {
                $customerModel->loadByEmail($input);
            }
            if (!$customerModel->getId()) {
                return false;
            }
            $customerModel->setData($code, $value);
            $customerModel->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Is login as customer module enabled(Magento_LoginAsCustomer is optional module)
     *
     * @return bool
     */
    public function isLoginAsCustomerModuleEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_LoginAsCustomer')
            && $this->moduleManager->isEnabled('Magento_LoginAsCustomerApi')
            && $this->moduleManager->isEnabled('Magento_LoginAsCustomerAssistance');
    }

    /**
     * Is login as customer valid(Magento_LoginAsCustomer is optional module)
     *
     * @param int|string $adminId
     * @param int|string $customerId
     * @return bool
     */
    public function isLoginAsCustomerValid($adminId, $customerId): bool
    {
        if (!$this->isLoginAsCustomerModuleEnabled()) {
            return false;
        }
        $isLoginAsCustomerEnabled = $this->objectManager->get(IsLoginAsCustomerEnabledForCustomerInterface::class);
        $isLoginAsCustomerSessionActive = $this->objectManager->get(IsLoginAsCustomerSessionActiveInterface::class);

        return $adminId
            && $customerId
            && $isLoginAsCustomerEnabled->execute($customerId)->isEnabled()
            && $isLoginAsCustomerSessionActive->execute($customerId, $adminId);
    }

    /**
     * Get backend only attribute codes
     *
     * @return string[]
     */
    public function getBackendOnlyAttributeCodes(): array
    {
        $result = [];
        foreach ($this->backendOnlyAttributeCodes as $item) {
            if (!(array_key_exists('disabled', $item) && $item['disabled'])) {
                $result[] = $item['code'];
            }
        }

        return $result;
    }
}
