<?php

namespace Rover\Notifier;

use Bitrix\Main\Context;
use Bitrix\Main\Diag\ExceptionHandlerLog;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use Throwable;

Loc::loadMessages(__FILE__);

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
     * Поля, которые модуль формирует сам; всё остальное из $context считается
     * произвольными плейсхолдерами шаблона и переживает очередь группировки в колонке DATA.
     */
    const RESERVED_FIELDS = [
        'MESSAGE', 'CODE', 'FILE', 'LINE', 'TRACE', 'LOG_TYPE', 'SOURCE', 'HOST', 'COUNT', 'DATE'
    ];

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @param array     $context
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function handle(Throwable $exception, $logType = '', array $context = []): void
    {
        if (!Options::isGroupingEnabled() || static::isCriticalLogType($logType)) {
            static::notify($exception, $logType, $context);
            return;
        }

        static::enqueue($exception, $logType, $context);
    }

    /**
     * Публичный API для стороннего кода (сайтовые обработчики, другие модули).
     * Сообщение проходит по тем же правилам, что и перехваченные ошибки: если перехват
     * выключен - не отправляется вовсе, если включена группировка - копится в очереди.
     * Перед вызовом нужно подключить модуль: Loader::includeModule('rover.notifier').
     *
     * @param Throwable|string $messageOrException готовое исключение либо произвольный текст
     * @param array            $context             доп. поля для шаблона письма/SMS (например, ['ORDER_ID' => 123]);
     *                                               LOG_TYPE задаёт метку типа (в т.ч. произвольную, вроде имени модуля)
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function report($messageOrException, array $context = []): void
    {
        if (!Options::isEnabled()) {
            return;
        }

        // источник не задан явно - определяем по вызывающему коду, чтобы модулям
        // не приходилось подписываться вручную
        if (!isset($context['SOURCE']) || $context['SOURCE'] === '') {
            $context['SOURCE'] = Source::detectCaller();
        }

        if ($messageOrException instanceof Throwable) {
            static::handle($messageOrException, $context['LOG_TYPE'] ?? '', $context);
            return;
        }

        static::handleMessage((string)$messageOrException, $context);
    }

    /**
     * Тот же маршрут, что и handle(), но для сообщения без исключения.
     *
     * @param string $message
     * @param array  $context
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function handleMessage(string $message, array $context): void
    {
        $logType = $context['LOG_TYPE'] ?? '';

        if (!Options::isGroupingEnabled() || static::isCriticalLogType($logType)) {
            static::send(static::buildFieldsFromMessage($message, $context));
            return;
        }

        static::enqueueData([
            'CLASS'    => '',
            'MESSAGE'  => $message,
            'CODE'     => (string)($context['CODE'] ?? ''),
            'FILE'     => (string)($context['FILE'] ?? ''),
            'LINE'     => (int)($context['LINE'] ?? 0),
            'TRACE'    => (string)($context['TRACE'] ?? ''),
            'LOG_TYPE' => (string)$logType,
            'SOURCE'   => (string)($context['SOURCE'] ?? Source::getCoreName()),
        ], $context);
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @param array     $context
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function notify(Throwable $exception, $logType = '', array $context = []): void
    {
        static::send(static::buildFields($exception, $logType, $context));
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
    protected static function isCriticalLogType($logType): bool
    {
        if (!Options::isCriticalImmediate('Y')) {
            return false;
        }

        // критичность определена только для числовых типов ядра; произвольная метка
        // из report() (например, имя модуля) критичной не считается
        if (!static::isCoreLogType($logType)) {
            return false;
        }

        return in_array((int)$logType, [ExceptionHandlerLog::FATAL, ExceptionHandlerLog::UNCAUGHT_EXCEPTION], true);
    }

    /**
     * @param mixed $logType
     * @return bool
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function isCoreLogType($logType): bool
    {
        return is_int($logType) || (is_string($logType) && $logType !== '' && ctype_digit($logType));
    }

    /**
     * Незаполненные CODE/FILE/LINE/TRACE (их не бывает у сообщений из report() без контекста)
     * заменяем понятной заглушкой, чтобы в письме не было "Message: текст ()" и "line 0".
     *
     * @param mixed $value
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function valueOrNotSet($value): string
    {
        // 0 для CODE и LINE означает "не задано" ровно так же, как пустая строка
        if (empty($value)) {
            return Loc::getMessage('rv-notifier__not_set') ?: 'not specified';
        }

        return (string)$value;
    }

    /**
     * Ядро отдаёт тип лога числом (см. константы ExceptionHandlerLog), в письме/SMS от него
     * толку нет - переводим в читаемое имя (FATAL, UNCAUGHT_EXCEPTION и т.д.).
     * Произвольную метку, переданную в report(), отдаём как есть.
     *
     * @param mixed $logType
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function formatLogType($logType): string
    {
        // report() без указания типа. Сравнение строгое: UNCAUGHT_EXCEPTION - это 0,
        // и empty()/нестрогая проверка ошибочно посчитали бы его незаполненным
        if ($logType === '' || $logType === null) {
            return static::valueOrNotSet('');
        }

        if (!static::isCoreLogType($logType)) {
            return (string)$logType;
        }

        return (string)ExceptionHandlerLog::logTypeToString((int)$logType);
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @param array     $context
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function enqueue(Throwable $exception, $logType, array $context = []): void
    {
        static::enqueueData([
            'CLASS'    => get_class($exception),
            'MESSAGE'  => $exception->getMessage(),
            'CODE'     => (string)$exception->getCode(),
            'FILE'     => $exception->getFile(),
            'LINE'     => $exception->getLine(),
            'TRACE'    => $exception->getTraceAsString(),
            'LOG_TYPE' => (string)$logType,
            'SOURCE'   => (string)($context['SOURCE'] ?? Source::detect($exception->getFile())),
        ], $context);
    }

    /**
     * @param array $data    CLASS/MESSAGE/FILE/LINE/TRACE/LOG_TYPE
     * @param array $context произвольные поля шаблона, переживают очередь в колонке DATA
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function enqueueData(array $data, array $context = []): void
    {
        $extra = array_diff_key($context, array_flip(self::RESERVED_FIELDS));

        // LOG_TYPE и SOURCE в хэше: одинаковый текст из разных источников или с разными
        // метками не должен слипаться; произвольные поля - чтобы не потерять контекст
        // первой попавшей в группу записи
        $hash = md5($data['CLASS'] . '|' . $data['MESSAGE'] . '|' . $data['CODE'] . '|' . $data['FILE'] . '|' .
            $data['LINE'] . '|' . $data['LOG_TYPE'] . '|' . $data['SOURCE'] . '|' . serialize($extra));
        $now  = new DateTime();

        $existing = ErrorTable::getList([
            'filter' => ['=HASH' => $hash],
            'select' => ['ID', 'CNT'],
            'limit'  => 1,
        ])->fetch();

        if ($existing) {
            ErrorTable::update($existing['ID'], [
                'CNT'       => $existing['CNT'] + 1,
                'DATE_LAST' => $now,
                'TRACE'     => $data['TRACE'],
            ]);

            return;
        }

        ErrorTable::add([
            'HASH'       => $hash,
            'MESSAGE'    => mb_substr($data['MESSAGE'], 0, 255),
            'CODE'       => mb_substr($data['CODE'], 0, 50),
            'FILE'       => mb_substr($data['FILE'], 0, 255),
            'LINE'       => (int)$data['LINE'],
            'LOG_TYPE'   => mb_substr($data['LOG_TYPE'], 0, 50),
            'SOURCE'     => mb_substr($data['SOURCE'], 0, 255),
            'TRACE'      => $data['TRACE'],
            'DATA'       => $extra ? static::encodeExtra($extra) : '',
            'DATE_FIRST' => $now,
            'DATE_LAST'  => $now,
            'CNT'        => 1,
        ]);
    }

    /**
     * @param array $extra
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function encodeExtra(array $extra): string
    {
        try {
            return Json::encode($extra);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @param Throwable $exception
     * @param mixed     $logType
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFields(Throwable $exception, $logType, array $context = []): array
    {
        // LOG_TYPE формируем сами (число ядра -> имя константы), из контекста его исключаем
        unset($context['LOG_TYPE']);

        return array_merge([
            'MESSAGE'  => $exception->getMessage(),
            'CODE'     => static::valueOrNotSet($exception->getCode()),
            'FILE'     => static::valueOrNotSet($exception->getFile()),
            'LINE'     => static::valueOrNotSet($exception->getLine()),
            'TRACE'    => static::valueOrNotSet($exception->getTraceAsString()),
            'LOG_TYPE' => static::formatLogType($logType),
            'SOURCE'   => Source::detect($exception->getFile()),
            'HOST'     => $_SERVER['HTTP_HOST'] ?? '',
            'COUNT'    => '1',
            'DATE'     => (string)(new DateTime()),
        ], $context);
    }

    /**
     * @param array $row
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFieldsFromRow(array $row): array
    {
        return array_merge([
            'MESSAGE'  => $row['MESSAGE'],
            'CODE'     => static::valueOrNotSet($row['CODE'] ?? ''),
            'FILE'     => static::valueOrNotSet($row['FILE']),
            'LINE'     => static::valueOrNotSet($row['LINE']),
            'TRACE'    => static::valueOrNotSet($row['TRACE']),
            'LOG_TYPE' => static::formatLogType($row['LOG_TYPE']),
            'SOURCE'   => (string)(($row['SOURCE'] ?? '') ?: Source::getCoreName()),
            'HOST'     => $_SERVER['HTTP_HOST'] ?? '',
            'COUNT'    => (string)$row['CNT'],
            'DATE'     => (string)$row['DATE_LAST'],
        ], static::decodeExtra($row['DATA'] ?? ''));
    }

    /**
     * @param string $data
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function decodeExtra($data): array
    {
        if (empty($data)) {
            return [];
        }

        try {
            return (array)Json::decode((string)$data);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param string $message
     * @param array  $context произвольные поля переопределяют значения по умолчанию
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function buildFieldsFromMessage(string $message, array $context): array
    {
        // CODE/FILE/LINE/TRACE берём из контекста, если переданы, иначе - заглушка;
        // из $context их убираем, чтобы array_merge не вернул пустые значения обратно
        $logType = $context['LOG_TYPE'] ?? '';
        $code    = $context['CODE'] ?? '';
        $file    = $context['FILE'] ?? '';
        $line    = $context['LINE'] ?? '';
        $trace   = $context['TRACE'] ?? '';
        unset($context['LOG_TYPE'], $context['CODE'], $context['FILE'], $context['LINE'], $context['TRACE']);

        return array_merge([
            'MESSAGE'  => $message,
            'CODE'     => static::valueOrNotSet($code),
            'FILE'     => static::valueOrNotSet($file),
            'LINE'     => static::valueOrNotSet($line),
            'TRACE'    => static::valueOrNotSet($trace),
            'LOG_TYPE' => static::formatLogType($logType),
            'SOURCE'   => Source::getCoreName(),
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
