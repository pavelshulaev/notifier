<?php

namespace Rover\Notifier;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Throwable;

Loc::loadMessages(__FILE__);

/**
 * Class Source
 *
 * Определяет источник сообщения по пути к файлу: ядро, конкретный модуль или код сайта.
 * Нужен, чтобы в письме/SMS было видно, откуда прилетела ошибка, без ручной подписи
 * со стороны вызывающего кода.
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Source
{
    /**
     * @param string|null $file
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function detect(?string $file): string
    {
        $path = static::toRelativePath($file);

        if ($path === '') {
            return static::getCoreName();
        }

        // /bitrix/modules/<id>/... либо /local/modules/<id>/...
        if (preg_match('#^/(?:bitrix|local)/modules/([^/]+)/#', $path, $matches)) {
            $moduleId = $matches[1];

            return $moduleId === 'main' ? static::getCoreName() : $moduleId;
        }

        // код самого сайта: php_interface, шаблоны, произвольные файлы в /local
        if (preg_match('#^/(?:bitrix|local)/(?:php_interface|templates)/#', $path)
            || str_starts_with($path, '/local/')) {
            return Loc::getMessage('rv-notifier__source_site') ?: 'Site';
        }

        if (str_starts_with($path, '/bitrix/')) {
            return static::getCoreName();
        }

        return Loc::getMessage('rv-notifier__source_site') ?: 'Site';
    }

    /**
     * Источник вызывающего кода: используется, когда Notifier::report() вызвали без явного SOURCE.
     *
     * @param int $limit глубина стека, в пределах которой ищем внешний вызов
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function detectCaller(int $limit = 8): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

        // ищем первый кадр за пределами самого rover.notifier - иначе источником
        // всегда оказывался бы сам модуль
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '' || str_contains(str_replace('\\', '/', $file), '/modules/rover.notifier/')) {
                continue;
            }

            return static::detect($file);
        }

        return static::getCoreName();
    }

    /**
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getCoreName(): string
    {
        return Loc::getMessage('rv-notifier__source_core') ?: 'Core';
    }

    /**
     * Приводит абсолютный путь к пути от корня сайта, с прямыми слэшами.
     *
     * @param string|null $file
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function toRelativePath(?string $file): string
    {
        if (empty($file)) {
            return '';
        }

        $file = str_replace('\\', '/', $file);

        try {
            $documentRoot = str_replace('\\', '/', (string)Application::getDocumentRoot());
        } catch (Throwable $e) {
            $documentRoot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        }

        if ($documentRoot !== '' && str_starts_with($file, $documentRoot)) {
            $file = mb_substr($file, mb_strlen($documentRoot));
        }

        return '/' . ltrim($file, '/');
    }
}
