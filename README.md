# Disable Customer Extension for Magento 2
<a href="https://www.wubinworks.com/disable-customer.html" target="_blank"><img src="https://raw.githubusercontent.com/wubinworks/home/master/images/Wubinworks/DisableCustomer/disable-customer.jpg" alt="Wubinworks Disable Customer" title="Wubinworks Disable Customer"/></a>

## Introduction
In various business situations, the store owners may want to disallow certain customers to login but they do not want to delete those accounts so they can re-enable them in the future.

Surprisingly, Magento 2 doesn't offer such a feature.

There are some ways that store owners tend to adopt but cannot truly "Disable" an account. The customer could always have workarounds.

 - Delete customer -> Register by using the same email again
 - Deactivate account -> Re-activate account via receiving confirmation email again
 - Lock account -> Unlock by resetting password

## Features
 - Disable/Enable customer:
   - Login
   - Password reset
   - Account activation(sending confirmation email)
 - Above works for both Frontend and WebAPI area
 - Disabled customer is forced to logout instantly and all WebAPI tokens are revoked
 - "Login As Customer" support: Yes. Admin can "Login As Customer" from backend even if the customer is disabled
 - Can set custom error message on a per-customer basis
 - Can set default error message as fallback
 - Admin customer grid filter option for filtering disabled/enabled customer

\*Note: the custom error message is displayed only if the customer provided correct credentials(i.e., Email and Password).

## Requirements & Compatibility
Only tested on **Magento 2.4 CE**\
This extension does not use `preference` and `template override`.

## Installation
**`composer require wubinworks/module-disable-customer`**

## Configuration
Admin Panel `Stores > Configuration > Wubinworks > Disable Customer`
 - `Default Disabled Customer Message`\
If you leave the `Disabled Customer Message` empty on customer editing page, this fallback message will be used.

By default, customer is not disabled.

To force a customer to logout immediately, use the `Disable -> Save -> Enable -> Save` trick.

## For Developers
##### Customer Attributes:
 - By default, all customer attributes added by this extension are "backend only attributes", which means they are invisible and unchangeable via `\Magento\Customer\Api\CustomerRepositoryInterface` in `Customer User Context`.

 - If you want to override "backend only attributes", create a small module and check `etc/di.xml` for instructions.

 - If you want to change "backend only attributes" value in `Customer User Context`(e.g., in your frontend controller), use `\Magento\Customer\Model\Customer` instead. You can also check `\Wubinworks\DisableCustomer\Helper\Customer::updateCustomerAttribute` for an example.

##### Events:
 - name: `wubinworks_disabled_customer_try_login`\
   data: 'customer' => `\Magento\Customer\Api\Data\CustomerInterface`\
   when: Disabled customer attempts to login with correct credentials

 - name: `wubinworks_customer_disabled`\
   data: 'customer' => `\Magento\Customer\Model\Customer`\
   when: After successfully setting customer to disabled

 - name: `wubinworks_customer_enabled`\
   data: 'customer' => `\Magento\Customer\Model\Customer`\
   when: After successfully setting customer to enabled

## ♥
If you like this extension please star this repository.

You may also like: [Disable Change Email for Magento 2](https://github.com/wubinworks/disable-change-email)
