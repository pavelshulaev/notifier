<?php

namespace Rover\Notifier;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\NotImplementedException;

Loc::loadMessages(__FILE__);

/**
 * Class Options
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 * @method static isEnabled()
 * @method static getEnabled()
 * @method static isGroupingEnabled()
 * @method static getGroupingEnabled()
 * @method static isCriticalImmediate($default = null)
 * @method static getGroupingInterval($default = null, $siteId = false)
 */
class Options
{
    const MODULE_ID = 'rover.notifier';

    /**
     * @param $methodName
     * @param $arguments
     * @return string|void
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException|NotImplementedException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function __callStatic($methodName, $arguments)
    {
        if (str_starts_with($methodName, 'get') || str_starts_with($methodName, 'set')) {
            $optionName = mb_substr($methodName, 3);
            $optionName = Tools::fromCamelCase($optionName);

            if (str_starts_with($methodName, 'get')) {
                return Option::get(self::MODULE_ID, $optionName, $arguments[0] ?? '', $arguments[1] ?? false);
            }

            Option::set(self::MODULE_ID, $optionName, $arguments[0], $arguments[1] ?? false);
            return;
        }

        if (str_starts_with($methodName, 'is')) {
            $getMethodName = 'get' . mb_substr($methodName, 2);

            return self::$getMethodName($arguments[0] ?? '', $arguments[1] ?? false) == 'Y';
        }

        throw new NotImplementedException($methodName);
    }
}
