<?php

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Diag\FileExceptionHandlerLog;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Rover\Notifier\Notifier;
use Rover\Notifier\Options;

/**
 * Class RoverNotifier
 *
 * Регистрируется в .settings.php (exception_handling.value.log) как дополнительный
 * обработчик логирования необработанных исключений и fatal error.
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */
class RoverNotifier extends FileExceptionHandlerLog
{
    /**
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function addToConfig(): void
    {
        $value                         = Configuration::getValue('exception_handling');
        $value['log']['class_name']    = __CLASS__;
        $value['log']['extension']     = '';
        $value['log']['required_file'] = __FILE__;

        Configuration::setValue('exception_handling', $value);
    }

    /**
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function removeFromConfig(): void
    {
        $value = Configuration::getValue('exception_handling');

        if (($value['log']['class_name'] ?? null) == __CLASS__) {
            unset($value['log']['class_name']);
            unset($value['log']['extension']);
            unset($value['log']['required_file']);

            Configuration::setValue('exception_handling', $value);
        }
    }

    /**
     * @param $exception
     * @param $logType
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws LoaderException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public function write($exception, $logType): void
    {
        parent::write($exception, $logType);

        if (!Loader::includeModule('rover.notifier')) {
            return;
        }

        if (defined('ROVER_NOTIFIER_HIT_SEND')) {
            return;
        }

        define('ROVER_NOTIFIER_HIT_SEND', true);

        if (!Options::isEnabled()) {
            return;
        }

        try {
            Notifier::handle($exception, $logType);
        } catch (\Throwable $e) {
            // обработчик исключений не должен падать сам по себе
        }
    }
}
