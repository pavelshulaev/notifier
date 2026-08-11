<?php

namespace Rover\Notifier;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Tabs
{
    const TAB__MAIN = 'main';

    const ENABLED            = 'enabled';
    const GROUPING_ENABLED   = 'grouping-enabled';
    const GROUPING_INTERVAL  = 'grouping-interval';
    const CRITICAL_IMMEDIATE = 'critical-immediate';

    /**
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getMap(): array
    {
        return [
            [
                'TAB'   => Loc::getMessage('rv-notifier__' . Tabs::TAB__MAIN . '_label'),
                'DIV'   => Tabs::TAB__MAIN,
                'TITLE' => Loc::getMessage('rv-notifier__' . Tabs::TAB__MAIN . '_descr'),
            ]
        ];
    }

    /**
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getAllOptions(): array
    {
        return [
            self::TAB__MAIN => self::getMain()
        ];
    }

    /**
     * @return array[]
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getMain(): array
    {
        return [
            [
                self::ENABLED, Loc::getMessage('rv-notifier__' . self::ENABLED . '_label'),
                '', ["checkbox"]
            ],
            [
                self::GROUPING_ENABLED,
                '&nbsp;<span id="' . self::GROUPING_ENABLED . '_hint"></span>&nbsp;' .
                Loc::getMessage('rv-notifier__' . self::GROUPING_ENABLED . '_label'),
                '', ["checkbox"]
            ],
            [
                self::GROUPING_INTERVAL, Loc::getMessage('rv-notifier__' . self::GROUPING_INTERVAL . '_label'),
                '3600', ['text', 10]
            ],
            [
                self::CRITICAL_IMMEDIATE, Loc::getMessage('rv-notifier__' . self::CRITICAL_IMMEDIATE . '_label'),
                'Y', ["checkbox"]
            ],
        ];
    }
}
