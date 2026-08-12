<?php

namespace Rover\Notifier;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Class ErrorTable
 *
 * Очередь сгруппированных ошибок (используется только при включённой группировке).
 *
 * @package Rover\Notifier
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class ErrorTable extends DataManager
{
    /**
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getTableName(): string
    {
        return 'rover_notifier_error';
    }

    /**
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),

            (new StringField('HASH'))
                ->configureSize(32)
                ->configureRequired(true),

            (new StringField('MESSAGE'))
                ->configureSize(255),

            (new StringField('CODE'))
                ->configureSize(50),

            (new StringField('FILE'))
                ->configureSize(255),

            (new IntegerField('LINE')),

            (new StringField('LOG_TYPE'))
                ->configureSize(50),

            (new StringField('SOURCE'))
                ->configureSize(255),

            (new TextField('TRACE')),

            // JSON с произвольными полями, переданными в Notifier::report() через $context:
            // без него при включённой группировке кастомные плейсхолдеры шаблона потерялись бы
            (new TextField('DATA')),

            (new DatetimeField('DATE_FIRST')),

            (new DatetimeField('DATE_LAST')),

            (new IntegerField('CNT'))
                ->configureDefaultValue(1),
        ];
    }
}
