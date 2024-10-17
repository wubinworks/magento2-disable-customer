<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Plugin\Customer\Api;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface as CustomerDataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\SecurityViolationException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Phrase;
use Wubinworks\DisableCustomer\Helper\System as SystemHelper;
use Wubinworks\DisableCustomer\Helper\Customer as CustomerHelper;

/**
 * AccountManagementInterface plugin
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class AccountManagement
{
    public const EVENT_DISABLED_CUSTOMER_TRY_LOGIN = 'wubinworks_disabled_customer_try_login';

    public const XML_PATH_DISABLEDCUSTOMER_GENERAL_DEFAULT_DISABLED_CUSTOMER_MESSAGE
        = 'wubinworks_disablecustomer/general/default_disabled_customer_message';

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
     * Check if customer is disabled after successful authentication
     *
     * @param AccountManagementInterface $subject
     * @param CustomerDataInterface $result
     * @param string $email
     * @return CustomerDataInterface
     *
     * @throws LocalizedException
     *
     * @see \Magento\Customer\Controller\Account\LoginPost::execute and more
     * @see /V1/integration/customer/token
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterAuthenticate(
        AccountManagementInterface $subject,
        CustomerDataInterface $result,
        $email
    ): CustomerDataInterface {
        try {
            $this->validateCustomer($email);
        } catch (LocalizedException $e) {
            $this->systemHelper->getEventManager()->dispatch(
                self::EVENT_DISABLED_CUSTOMER_TRY_LOGIN,
                ['customer' => $result]
            );
            throw $e;
        }

        return $result;
    }

    /**
     * Intercept account activation
     *
     * @param AccountManagementInterface $subject
     * @param string $email
     * @return null
     *
     * @throws \Exception
     *
     * @see \Magento\Customer\Controller\Account\Confirm::execute
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeActivate(
        AccountManagementInterface $subject,
        $email
    ) {
        $this->validateCustomer($email, \Exception::class, true);
        return null;
    }

    /**
     * Intercept account activation
     *
     * @param AccountManagementInterface $subject
     * @param string $customerId
     * @return null
     *
     * @throws LocalizedException
     *
     * @see /V1/customers/me/activate requires customer token
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeActivateById(
        AccountManagementInterface $subject,
        $customerId
    ) {
        $this->validateCustomer($customerId, LocalizedException::class);
        return null;
    }

    /**
     * Intercept requesting a confirmation(activation) email
     *
     * @param AccountManagementInterface $subject
     * @param string $email
     * @return null
     *
     * @throws NoSuchEntityException
     *
     * @see \Magento\Customer\Controller\Account\Confirmation::execute
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeResendConfirmation(
        AccountManagementInterface $subject,
        $email
    ) {
        $this->validateCustomer($email, NoSuchEntityException::class, true);
        return null;
    }

    /**
     * Intercept resetting password
     *
     * @param AccountManagementInterface $subject
     * @param string $email
     * @return null
     *
     * @throws InputException
     *
     * @see \Magento\Customer\Controller\Account\ResetPasswordPost::execute
     * @see /V1/customers/resetPassword
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeResetPassword(
        AccountManagementInterface $subject,
        $email
    ) {
        $this->validateCustomer($email, InputException::class);
        return null;
    }

    /**
     * Intercept password reset link token validation
     *
     * @param AccountManagementInterface $subject
     * @param string $customerId
     * @return null
     *
     * @throws \Exception
     *
     * @see \Magento\Customer\Controller\Account\CreatePassword::execute
     * @see /V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeValidateResetPasswordLinkToken(
        AccountManagementInterface $subject,
        $customerId
    ) {
        $this->validateCustomer($customerId, \Exception::class, true);
        return null;
    }

    /**
     * Intercept initiating password reset
     *
     * @param AccountManagementInterface $subject
     * @param string $email
     * @return null
     *
     * @throws SecurityViolationException
     *
     * @see \Magento\Customer\Controller\Account\ForgotPasswordPost::execute
     * @see /V1/customers/password
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeInitiatePasswordReset(
        AccountManagementInterface $subject,
        $email
    ) {
        $this->validateCustomer($email, SecurityViolationException::class);
        return null;
    }

    /**
     * Get disabled customer message in a fallback chain
     *
     * @param string $message
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function getFallbackMessage(string $message): string
    {
        $messages[] = $message;
        $messages[] = $this->getDefaultDisabledCustomerMessage();
        $messages[] = 'Your account has been disabled. Please contact the store owner.';
        foreach ($messages as $str) {
            if ($str !== '') {
                return $str;
            }
        }

        throw new \InvalidArgumentException('All messages are empty.');
    }

    /**
     * Get default disabled customer message
     *
     * @return string
     */
    protected function getDefaultDisabledCustomerMessage(): string
    {
        return (string)$this->systemHelper->getConfig(
            self::XML_PATH_DISABLEDCUSTOMER_GENERAL_DEFAULT_DISABLED_CUSTOMER_MESSAGE
        );
    }

    /**
     * Check if customer is disabled and throw proper exception
     *
     * @param string|int $input
     * @param string $exceptionClass
     * @param bool $needAddErrorMessage
     * @return void
     *
     * @throws LocalizedException dynamic
     * @throws \Exception
     *
     * @throws \InvalidArgumentException
     */
    protected function validateCustomer(
        $input,
        string $exceptionClass = LocalizedException::class,
        bool $needAddErrorMessage = false
    ): void {
        if ($this->customerHelper->isCustomerDisabled($input)) {
            $message = new Phrase($this->getFallbackMessage(
                $this->customerHelper->getDisabledCustomerMessage($input)
            ));
            if ($needAddErrorMessage) {
                $this->systemHelper->getMessageManager()->addErrorMessage($message);
            }

            if ($exceptionClass === LocalizedException::class
                || is_subclass_of($exceptionClass, LocalizedException::class)) {
                    throw new $exceptionClass($message);
            }

            if ($exceptionClass === \Exception::class
                || is_subclass_of($exceptionClass, \Exception::class)) {
                    throw new $exceptionClass($message->getText());
            }

            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $exceptionClass must be \Exception or its sub class. %s given.',
                    $exceptionClass
                )
            );
        }
    }
}
