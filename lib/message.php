<?php

namespace Rover\Notifier;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Message
{
    const TYPE__OK    = 'OK';
    const TYPE__ERROR = 'ERROR';
    const TYPE__INFO  = 'INFO';

    protected static array $messages = [];

    /**
     * @param        $message
     * @param string $type
     * @param bool   $html
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function add($message, string $type = self::TYPE__OK, bool $html = false): void
    {
        if (is_array($message)) {
            $message = implode($html ? '<br>' : "\n", $message);
        }

        if (!$html) {
            $message = htmlspecialcharsbx($message);
        }

        self::$messages[] = [
            'MESSAGE' => trim($message),
            'HTML'    => $html,
            'TYPE'    => htmlspecialcharsbx($type),
        ];
    }

    /**
     * @param      $message
     * @param bool $html
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function addOk($message, bool $html = false): void
    {
        self::add($message, self::TYPE__OK, $html);
    }

    /**
     * @param      $message
     * @param bool $html
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function addError($message, bool $html = false): void
    {
        self::add($message, self::TYPE__ERROR, $html);
    }

    /**
     * @param      $message
     * @param bool $html
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function addInfo($message, bool $html = false): void
    {
        self::add($message, self::TYPE__INFO, $html);
    }

    /**
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function get(): array
    {
        return self::$messages;
    }

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function clear(): void
    {
        self::$messages = [];
    }

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function showAdmin(): void
    {
        $messages    = self::get();
        $messagesCnt = count($messages);

        if (!$messagesCnt) {
            return;
        }

        for ($i = 0; $i < $messagesCnt; ++$i) {

            if ($messages[$i]['TYPE'] == self::TYPE__INFO):
                ?>
                <div class="adm-info-message-wrap" style="position: relative; top: -15px;">
                <div class="adm-info-message"><?
                    echo htmlspecialcharsbx($messages[$i]['MESSAGE']);
                    ?></div>
                </div><?php
            else:
                echo (new \CAdminMessage($messages[$i]))->Show();
            endif;
        }
    }
}
