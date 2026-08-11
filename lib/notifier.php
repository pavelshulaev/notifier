<?php

namespace Rover\Notifier;

use Bitrix\Main\Context;
use Bitrix\Main\Diag\ExceptionHandlerLog;
use Bitrix\Main\Loader;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Type\DateTime;
use Throwable;

/**
 * Class Notifier
 *
 * Не отправляет письма/сообщения сама — только дёргает стандартные события Bitrix
 * (почтовое событие и SMS-событие). Кто и куда реально доставит сообщение, решают
 * уже установленные на сайте интеграции (шаблон почтового события, SMS-провайдер и т.п.).
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Notifier
{
    const EVENT_NAME = 'ROVER_NOTIFIER_ERROR';

    // отдельное имя события для SMS/мессенджеров: у Bitrix "тип события" уникален
    // по (LID, EVENT_NAME) независимо от EVENT_TYPE, поэтому одно и то же имя нельзя
    // зарегистрировать одновременно и как email-, и как sms-тип (см. install/index.php)
    const SMS_EVENT_NAME = 'ROVER_NOTIFIER_ERROR_SMS';

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function handle(Throwable $exception, $logType = ''): void
    {
        if (!Options::isGroupingEnabled() || static::isCriticalLogType($logType)) {
            static::notify($exception, $logType);
            return;
        }

        static::enqueue($exception, $logType);
    }

    /**
     * Публичный API для стороннего кода (сайтовые обработчики, другие модули): шлёт
     * сообщение немедленно, минуя очередь группировки, независимо от настроек модуля.
     * Перед вызовом нужно подключить модуль: Loader::includeModule('rover.notifier').
     *
     * @param Throwable|string $messageOrException готовое исключение либо произвольный текст
     * @param array            $context             доп. поля для шаблона письма/SMS (например, ['ORDER_ID' => 123]);
     *                                               для Throwable - LOG_TYPE, если нужно его проставить явно
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function report($messageOrException, array $context = []): void
    {
        if ($messageOrException instanceof Throwable) {
            static::notify($messageOrException, $context['LOG_TYPE'] ?? '');
            return;
        }

        static::send(static::buildFieldsFromMessage((string)$messageOrException, $context));
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function notify(Throwable $exception, $logType = ''): void
    {
        static::send(static::buildFields($exception, $logType));
    }

    /**
     * @param array $row строка из ErrorTable
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function notifyGrouped(array $row): void
    {
        static::send(static::buildFieldsFromRow($row));
    }

    /**
     * Критичность по логике перехватчика Bitrix (FATAL/UNCAUGHT_EXCEPTION), при включённой
     * настройке "критичные сразу" заставляет handle() отправить немедленно даже при
     * включённой группировке.
     *
     * @param mixed $logType
     * @return bool
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function isCriticalLogType(int $logType): bool
    {
        if (!Options::isCriticalImmediate('Y')) {
            return false;
        }

        return in_array($logType, [ExceptionHandlerLog::FATAL, ExceptionHandlerLog::UNCAUGHT_EXCEPTION], true);
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function enqueue(Throwable $exception, $logType): void
    {
        $hash = md5(get_class($exception) . '|' . $exception->getMessage() . '|' . $exception->getFile() . '|' .
            $exception->getLine());
        $now = new DateTime();

        $existing = ErrorTable::getList([
            'filter' => ['=HASH' => $hash],
            'select' => ['ID', 'CNT'],
            'limit'  => 1,
        ])->fetch();

        if ($existing) {
            ErrorTable::update($existing['ID'], [
                'CNT'       => $existing['CNT'] + 1,
                'DATE_LAST' => $now,
                'TRACE'     => $exception->getTraceAsString(),
            ]);

            return;
        }

        ErrorTable::add([
            'HASH'       => $hash,
            'MESSAGE'    => mb_substr($exception->getMessage(), 0, 255),
            'FILE'       => mb_substr($exception->getFile(), 0, 255),
            'LINE'       => $exception->getLine(),
            'LOG_TYPE'   => (string)$logType,
            'TRACE'      => $exception->getTraceAsString(),
            'DATE_FIRST' => $now,
            'DATE_LAST'  => $now,
            'CNT'        => 1,
        ]);
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFields(Throwable $exception, $logType): array
    {
        return [
            'MESSAGE'  => $exception->getMessage(),
            'CODE'     => (string)$exception->getCode(),
            'FILE'     => $exception->getFile(),
            'LINE'     => (string)$exception->getLine(),
            'TRACE'    => $exception->getTraceAsString(),
            'LOG_TYPE' => (string)$logType,
            'HOST'     => $_SERVER['HTTP_HOST'] ?? '',
            'COUNT'    => '1',
            'DATE'     => (string)(new DateTime()),
        ];
    }

    /**
     * @param array $row
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFieldsFromRow(array $row): array
    {
        return [
            'MESSAGE'  => $row['MESSAGE'],
            'CODE'     => '',
            'FILE'     => $row['FILE'],
            'LINE'     => (string)$row['LINE'],
            'TRACE'    => (string)$row['TRACE'],
            'LOG_TYPE' => (string)$row['LOG_TYPE'],
            'HOST'     => $_SERVER['HTTP_HOST'] ?? '',
            'COUNT'    => (string)$row['CNT'],
            'DATE'     => (string)$row['DATE_LAST'],
        ];
    }

    /**
     * @param string $message
     * @param array  $context произвольные поля переопределяют значения по умолчанию
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFieldsFromMessage(string $message, array $context): array
    {
        return array_merge([
            'MESSAGE'  => $message,
            'CODE'     => '',
            'FILE'     => '',
            'LINE'     => '',
            'TRACE'    => '',
            'LOG_TYPE' => '',
            'HOST'     => $_SERVER['HTTP_HOST'] ?? '',
            'COUNT'    => '1',
            'DATE'     => (string)(new DateTime()),
        ], $context);
    }

    /**
     * @param array $fields
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function send(array $fields): void
    {
        $siteId = static::getSiteId();

        try {
            \CEvent::Send(self::EVENT_NAME, $siteId, $fields);
        } catch (Throwable $e) {
            // почтовый транспорт не должен ронять обработчик ошибок
        }

        if (!Loader::includeModule('messageservice')) {
            return;
        }

        try {
            (new \Bitrix\Main\Sms\Event(self::SMS_EVENT_NAME, $fields))->setSite($siteId)->send();
        } catch (Throwable $e) {
            // если шаблон SMS-события не настроен - молча пропускаем
        }
    }

    /**
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getSiteId(): string
    {
        try {
            $siteId = Context::getCurrent()->getSite();
        } catch (Throwable $e) {
            $siteId = null;
        }

        if ($siteId) {
            return $siteId;
        }

        $site = SiteTable::getList([
            'filter' => ['=DEF' => 'Y'],
            'select' => ['LID'],
            'limit'  => 1,
        ])->fetch();

        return $site['LID'] ?? (defined('SITE_ID') ? SITE_ID : 's1');
    }
}
