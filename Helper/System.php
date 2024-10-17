<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\DisableCustomer\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

/**
 * System helper
 */
class System extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @var UserContextInterface
     */
    protected $userContext;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * Application Event Dispatcher
     *
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * Constructor
     *
     * @param AppState $appState
     * @param UserContextInterface $userContext
     * @param MessageManagerInterface $messageManager
     * @param ModuleManager $moduleManager
     * @param EventManagerInterface $eventManager
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        AppState $appState,
        UserContextInterface $userContext,
        MessageManagerInterface $messageManager,
        ModuleManager $moduleManager,
        EventManagerInterface $eventManager,
        \Magento\Framework\App\Helper\Context $context
    ) {
        parent::__construct($context);
        $this->appState = $appState;
        $this->userContext= $userContext;
        $this->messageManager = $messageManager;
        $this->moduleManager = $moduleManager;
        $this->eventManager = $eventManager;
    }

    /**
     * Get current store system configuration value
     *
     * @param string $path
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return mixed
     */
    public function getConfig($path, $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            $scopeType,
            $scopeCode
        );
    }

    /**
     * Get current area code.
     *
     * @return string
     */
    public function getArea(): string
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            $areaCode = 'unknown';
        }

        return $areaCode;
    }

    /**
     * Is admin or integration context
     *
     * @return bool
     */
    public function isAdminOrIntegration(): bool
    {
        return in_array(
            $this->userContext->getUserType(),
            [UserContextInterface::USER_TYPE_INTEGRATION, UserContextInterface::USER_TYPE_ADMIN]
        );
    }

    /**
     * Is in webapi area
     *
     * @return bool
     */
    public function isWebapi(): bool
    {
        return in_array(
            $this->getArea(),
            [Area::AREA_WEBAPI_REST, Area::AREA_WEBAPI_SOAP, Area::AREA_GRAPHQL]
        );
    }

    /**
     * Get current full action name
     *
     * @param string $delimiter
     * @return string|null
     */
    public function getFullActionName(string $delimiter = '/')
    {
        if ($this->getArea() === Area::AREA_FRONTEND
            || $this->getArea() === Area::AREA_ADMINHTML) {
            return $this->_request->getFullActionName($delimiter);
        }

        return null;
    }

    /**
     * Get message manager
     *
     * @return MessageManagerInterface
     */
    public function getMessageManager(): MessageManagerInterface
    {
        return $this->messageManager;
    }

    /**
     * Get user context object
     *
     * @return UserContextInterface
     */
    public function getUserContextObject(): UserContextInterface
    {
        return $this->userContext;
    }

    /**
     * Is module enabled
     *
     * @param string $name
     * @return bool
     */
    public function isModuleEnabled(string $name): bool
    {
        return $this->moduleManager->isEnabled($name);
    }

    /**
     * Get event manager
     *
     * @return EventManagerInterface
     */
    public function getEventManager(): EventManagerInterface
    {
        return $this->eventManager;
    }

    /**
     * Get current datetime string in UTC
     *
     * @return string
     */
    public function gmNow(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Create PHP DateTime object in UTC, return false for malformed string
     *
     * @param mixed $str
     * @return \DateTime|bool
     */
    public function createDateTimeObject($str)
    {
        if (!$str) {
            return false;
        }
        try {
            return new \DateTime($str, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return false;
        }
    }
}
