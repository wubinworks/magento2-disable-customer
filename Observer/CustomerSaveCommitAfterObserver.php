<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Wubinworks\DisableCustomer\Helper\System as SystemHelper;
use Wubinworks\DisableCustomer\Helper\Customer as CustomerHelper;
use Wubinworks\DisableCustomer\Setup\Patch\Data\AddCustomerAttributes;

/**
 * Customer model save commit after event observer
 */
class CustomerSaveCommitAfterObserver implements \Magento\Framework\Event\ObserverInterface
{
    public const EVENT_CUSTOMER_DISABLED = 'wubinworks_customer_disabled';
    public const EVENT_CUSTOMER_ENABLED = 'wubinworks_customer_enabled';

    /**
     * @var SystemHelper
     */
    protected $systemHelper;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Constructor
     *
     * @param SystemHelper $systemHelper
     * @param CustomerHelper $customerHelper
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        SystemHelper $systemHelper,
        CustomerHelper $customerHelper,
        ObjectManagerInterface $objectManager
    ) {
        $this->systemHelper = $systemHelper;
        $this->customerHelper = $customerHelper;
        $this->objectManager = $objectManager;
    }

    /**
     * Revoke customer WebAPI token and record disabled time
     *
     * @param Observer $observer
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getCustomer();
        // Check if attribute was changed from false to true
        if ($customer->getData(AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED)
            && !$customer->getOrigData(AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED)) {
            // Revoke WebAPI tokens
            if ($this->systemHelper->isModuleEnabled('Magento_Integration')) {
                $tokenService = $this->objectManager->get(CustomerTokenServiceInterface::class);
                try {
                    $tokenService->revokeCustomerAccessToken($customer->getId());
                    // Don't add message for mass action
                    if (!$this->isMassAction()) {
                        $this->systemHelper->getMessageManager()->addSuccessMessage(
                            __('You have revoked the customer\'s WebAPI tokens.')
                        );
                    }
                } catch (\Exception $e) {
                    $this->systemHelper->getMessageManager()->addErrorMessage($e->getMessage());
                }
            }

            // Record disabled time
            if (!$this->customerHelper->updateCustomerAttribute(
                $customer->getId(),
                AddCustomerAttributes::CUSTOMER_ATTR_DISABLED_AT,
                $this->systemHelper->gmNow()
            )
                ) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'Cannot save attribute %s for customer id %d',
                        AddCustomerAttributes::CUSTOMER_ATTR_DISABLED_AT,
                        (int)$customer->getId()
                    )
                );
            }

            $this->systemHelper->getEventManager()->dispatch(
                self::EVENT_CUSTOMER_DISABLED,
                ['customer' => $customer]
            );
        } elseif (!$customer->getData(AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED)
            && $customer->getOrigData(AddCustomerAttributes::CUSTOMER_ATTR_IS_DISABLED)) {
            $this->systemHelper->getEventManager()->dispatch(
                self::EVENT_CUSTOMER_ENABLED,
                ['customer' => $customer]
            );
        }
    }

    /**
     * Check if current action is mass action
     *
     * @return bool
     */
    protected function isMassAction(): bool
    {
        return $this->systemHelper->getArea() === \Magento\Framework\App\Area::AREA_ADMINHTML
            && $this->systemHelper->getFullActionName() === 'customer/index/inlineedit';
    }
}
