<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Sms\TemplateTable;
use Rover\Notifier\Agent;
use Rover\Notifier\ErrorTable;
use Rover\Notifier\Notifier;
use Rover\Notifier\Options;

Loc::LoadMessages(__FILE__);

/**
 * Class rover_notifier
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */
class rover_notifier extends CModule
{
    var $MODULE_ID = "rover.notifier";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;

    function __construct()
    {
        global $rnErrors;

        $arModuleVersion = [];
        $rnErrors        = [];

        require(__DIR__ . "/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION      = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        } else {
            $rnErrors[] = Loc::getMessage('rv-notifier__version_info_error');
        }

        $this->MODULE_NAME        = Loc::getMessage("rv-notifier__MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("rv-notifier__MODULE_DESC");
        $this->PARTNER_NAME       = Loc::getMessage("rv-notifier__PARTNER_NAME");
        $this->PARTNER_URI        = Loc::getMessage("rv-notifier__PARTNER_URI");
    }

    /**
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    function DoInstall(): void
    {
        global $rnErrors, $APPLICATION;

        try {
            ModuleManager::registerModule($this->MODULE_ID);

            if (!Loader::includeModule($this->MODULE_ID)) {
                throw new \Exception('Module not found after registration');
            }

            $this->installEventType();
            $this->installSmsTemplate();
            $this->installDb();
            $this->installAgent();
        } catch (\Throwable $e) {
            ModuleManager::unRegisterModule($this->MODULE_ID);

            $rnErrors[] = $e->getMessage();
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('rv-notifier__install_title', ['#name#' => $this->MODULE_NAME]),
            __DIR__ . '/message.php'
        );
    }

    /**
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    function DoUninstall(): void
    {
        global $rnErrors, $APPLICATION, $step;

        if ((int)$step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('rv-notifier__uninstall_title', ['#name#' => $this->MODULE_NAME]),
                __DIR__ . '/unstep.php'
            );

            return;
        }

        $keepEvents = Application::getInstance()->getContext()->getRequest()->get('keep_events') == 'Y';

        try {
            if (Loader::includeModule($this->MODULE_ID)) {
                RoverNotifier::removeFromConfig();

                if (!$keepEvents) {
                    $this->removeEventTypesAndTemplates();
                }

                if (ErrorTable::getEntity()->getConnection()->isTableExists(ErrorTable::getTableName())) {
                    ErrorTable::getEntity()->getConnection()->dropTable(ErrorTable::getTableName());
                }
            }

            \CAgent::RemoveModuleAgents($this->MODULE_ID);

            ModuleManager::unRegisterModule($this->MODULE_ID);
        } catch (\Throwable $e) {
            $rnErrors[] = $e->getMessage();
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('rv-notifier__uninstall_title', ['#name#' => $this->MODULE_NAME]),
            __DIR__ . '/unMessage.php'
        );
    }

    /**
     * Удаляет созданные модулем типы событий (почтовый и sms), почтовый шаблон
     * и SMS-шаблоны для sms-события модуля. Вызывается при удалении модуля,
     * если не отмечена галочка "оставить события и шаблоны".
     *
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected function removeEventTypesAndTemplates(): void
    {
        $dbMessage = \CEventMessage::GetList('ID', 'DESC', ['EVENT_NAME' => Notifier::EVENT_NAME]);
        while ($message = $dbMessage->Fetch()) {
            \CEventMessage::Delete($message['ID']);
        }

        $res = TemplateTable::getList(['select' => ['ID'], 'filter' => ['=EVENT_NAME' => Notifier::SMS_EVENT_NAME]]);
        while ($row = $res->fetch()) {
            TemplateTable::delete($row['ID']);
        }

        \CEventType::Delete(['EVENT_NAME' => Notifier::EVENT_NAME]);
        \CEventType::Delete(['EVENT_NAME' => Notifier::SMS_EVENT_NAME]);
    }

    /**
     * Регистрирует типы события (почтовый и sms) и создаёт шаблон письма по-умолчанию,
     * чтобы модуль работал сразу после установки без дополнительной настройки.
     *
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected function installEventType(): void
    {
        $this->registerEventType(Notifier::EVENT_NAME, EventTypeTable::TYPE_EMAIL,
            'rv-notifier__event_type_name_mail', 'rv-notifier__event_type_description_mail');

        $this->registerEventType(Notifier::SMS_EVENT_NAME, EventTypeTable::TYPE_SMS,
            'rv-notifier__event_type_name_sms', 'rv-notifier__event_type_description_sms');

        $eventName = Notifier::EVENT_NAME;

        $dbMessage = \CEventMessage::GetList('ID', 'DESC', ['EVENT_NAME' => $eventName]);
        if ($dbMessage->Fetch()) {
            return;
        }

        $site  = SiteTable::getList(['filter' => ['=DEF' => 'Y'], 'select' => ['LID', 'EMAIL'], 'limit' => 1])
            ->fetch();
        $lid   = $site['LID'] ?? 's1';
        $email = $site['EMAIL'] ?: ('admin@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        (new \CEventMessage())->Add([
            'ACTIVE'     => 'Y',
            'EVENT_NAME' => $eventName,
            'LID'        => [$lid],
            'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO'   => $email,
            'SUBJECT'    => '#HOST#: #MESSAGE#',
            'BODY_TYPE'  => 'text',
            'MESSAGE'    => "Message: #MESSAGE# (#CODE#)\r\n\r\nFile: #FILE#, line #LINE#\r\n\r\n" .
                "Count: #COUNT#\r\nDate: #DATE#\r\n\r\nTrace:\r\n#TRACE#",
        ]);
    }

    /**
     * Создаёт SMS-шаблон для sms-события модуля. Получателя (телефон/chat_id) знает
     * только сам админ, поэтому шаблон создаётся неактивным с плейсхолдером в поле
     * "Получатель" - его нужно донастроить и включить в стандартном разделе SMS-шаблонов.
     *
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected function installSmsTemplate(): void
    {
        $existing = TemplateTable::getList([
            'select' => ['ID'],
            'filter' => ['=EVENT_NAME' => Notifier::SMS_EVENT_NAME],
            'limit'  => 1,
        ])->fetch();

        if ($existing) {
            return;
        }

        $site = SiteTable::getList(['filter' => ['=DEF' => 'Y'], 'select' => ['LID'], 'limit' => 1])->fetch();
        $lid  = $site['LID'] ?? 's1';

        $template = TemplateTable::getEntity()->createObject();
        $template->set('EVENT_NAME', Notifier::SMS_EVENT_NAME);
        $template->set('ACTIVE', false);
        $template->set('SENDER', '#DEFAULT_SENDER#');
        $template->set('RECEIVER', '+7XXXXXXXXXX');
        $template->set('MESSAGE', '#HOST#: #MESSAGE# (#FILE#:#LINE#), x#COUNT#');

        $template->addToSites(SiteTable::getEntity()->wakeUpObject($lid));

        $template->save();
    }

    /**
     * @param string $eventName
     * @param string $eventType    EventTypeTable::TYPE_EMAIL | EventTypeTable::TYPE_SMS
     * @param string $nameLocKey
     * @param string $descriptionLocKey
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected function registerEventType(
        string $eventName,
        string $eventType,
        string $nameLocKey,
        string $descriptionLocKey
    ): void {
        $dbEventType = \CEventType::GetList(['LID' => 'ru', 'EVENT_NAME' => $eventName]);
        if ($dbEventType->Fetch()) {
            return;
        }

        foreach (['ru', 'en'] as $lid) {
            \CEventType::Add([
                'LID'         => $lid,
                'EVENT_NAME'  => $eventName,
                'EVENT_TYPE'  => $eventType,
                'NAME'        => Loc::getMessage($nameLocKey),
                'DESCRIPTION' => Loc::getMessage($descriptionLocKey),
            ]);
        }
    }

    /**
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public function installDb(): void
    {
        if (!ErrorTable::getEntity()->getConnection()->isTableExists(ErrorTable::getTableName())) {
            ErrorTable::getEntity()->createDbTable();
        }
    }

    /**
     * @return void
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected function installAgent(): void
    {
        \CAgent::AddAgent(
            Agent::class . '::flush();',
            $this->MODULE_ID,
            'N',
            (int)Options::getGroupingInterval(3600)
        );
    }
}
