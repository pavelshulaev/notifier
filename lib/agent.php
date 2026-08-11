<?php

namespace Rover\Notifier;

use Throwable;

/**
 * Class Agent
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Agent
{
    /**
     * Разбирает накопленную очередь сгруппированных ошибок и шлёт их одним сообщением на каждую.
     * Интервал запуска задаётся настройкой grouping-interval (см. install/index.php и options.php).
     *
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function flush(): string
    {
        if (Options::isGroupingEnabled()) {
            static::flushQueue();
        }

        return __CLASS__ . '::flush();';
    }

    /**
     * Немедленно рассылает всё, что накопилось в очереди, независимо от настройки группировки.
     * Используется агентом (flush) и при отключении группировки в настройках (options.php),
     * чтобы уже накопленные ошибки не остались лежать в таблице без отправки.
     *
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function flushQueue(): void
    {
        $res = ErrorTable::getList(['select' => ['*']]);
        while ($row = $res->fetch()) {
            try {
                Notifier::notifyGrouped($row);
            } catch (Throwable $e) {
                // не прерываем разбор остальной очереди из-за одной неудачной отправки
            }

            ErrorTable::delete($row['ID']);
        }
    }
}
