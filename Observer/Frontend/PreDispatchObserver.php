<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Wubinworks\DisableCustomer\Helper\System as SystemHelper;
use Wubinworks\DisableCustomer\Helper\Customer as CustomerHelper;

/**
 * Pre dispatch event observer
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PreDispatchObserver implements \Magento\Framework\Event\ObserverInterface
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
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * Constructor
     *
     * @param SystemHelper $systemHelper
     * @param CustomerHelper $customerHelper
     * @param CustomerSession $customerSession
     */
    public function __construct(
        SystemHelper $systemHelper,
        CustomerHelper $customerHelper,
        CustomerSession $customerSession
    ) {
        $this->systemHelper = $systemHelper;
        $this->customerHelper = $customerHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * Logout customer if needed
     *
     * @param Observer $observer
     * @return void
     *
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @see typo at https://github.com/magento/magento2/blob/a4cf5e62468770fdb9f7b48938f6644ae354a51b/app/code/Magento/LoginAsCustomer/Model/SetLoggedAsCustomerAdminId.php#L38
     */
    public function execute(Observer $observer): void
    {
        // Not logged in or "LoginAsCustomer"
        if (!$this->customerSession->isLoggedIn()
            || $this->customerHelper->isLoginAsCustomerValid(
                $this->customerSession->getLoggedAsCustomerAdmindId() // typo
                        ?: $this->customerSession->getLoggedAsCustomerAdminId(),
                $this->customerSession->getLoggedAsCustomerCustomerId()
            )
        ) {
            return;
        }

        $customerId = $this->customerSession->getCustomerId();
        // Absolutely disabled
        if ($this->customerHelper->isCustomerDisabled($customerId)) {
            $this->customerSession->logout();
            return;
        }

        /**
         * Forced logout feature
         * Already logged in and need to check disabled time in the past
         */
        $disabledAt = $this->systemHelper->createDateTimeObject(
            $this->customerHelper->getCustomerDisabledAt($customerId)
        );
        if (!$disabledAt) {
            return;
        }

        $loggedInAt = $this->systemHelper->createDateTimeObject(
            $this->customerSession->getWwLoggedInAt()
        );
        if (!$loggedInAt || $loggedInAt <= $disabledAt) {
            $this->customerSession->logout();
        }
    }
}
