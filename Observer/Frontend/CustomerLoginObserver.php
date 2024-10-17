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
 * Customer login event observer
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class CustomerLoginObserver implements \Magento\Framework\Event\ObserverInterface
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
     * Set ww_logged_in_at time to session. A customer can have multiple sessions
     *
     * @param Observer $observer
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        // There may exist other observers
        if ($this->customerSession->isLoggedIn()) {
            $this->customerSession->setWwLoggedInAt($this->systemHelper->gmNow());
        }
    }
}
