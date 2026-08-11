<?php
/**
 * @var string $mid
 * @var \CMain $APPLICATION
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Sms\TemplateTable;
use Rover\Notifier\Agent;
use Rover\Notifier\ErrorTable;
use Rover\Notifier\Message;
use Rover\Notifier\Notifier;
use Rover\Notifier\Options;
use Rover\Notifier\Tabs;

Loc::LoadMessages(__FILE__);
Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/options.php");


if (!Loader::includeModule($mid)) {
    ShowError(Loc::getMessage('rv-notifier__module-not-found', ['#mid#' => $mid]));

    return;
}


$arAllOptions = Tabs::getAllOptions();
$aTabs        = Tabs::getMap();


if ((isset($_REQUEST['save']) || isset($_REQUEST['apply'])) && check_bitrix_sessid()) {

    if (isset($_REQUEST['enabled']) && $_REQUEST['enabled'] == 'Y' && !Options::isEnabled()) {
        RoverNotifier::addToConfig();
    }

    if ((!isset($_REQUEST['enabled']) || $_REQUEST['enabled'] != 'Y') && Options::isEnabled()) {
        RoverNotifier::removeFromConfig();
    }

    // группировку выключили - то, что уже накопилось в очереди, разослать сразу, а не хранить до следующего agent'а
    if ((!isset($_REQUEST[Tabs::GROUPING_ENABLED]) || $_REQUEST[Tabs::GROUPING_ENABLED] != 'Y')
        && Options::isGroupingEnabled()) {
        Agent::flushQueue();
    }

    foreach ($aTabs as $tab) {
        __AdmSettingsSaveOptions($mid, $arAllOptions[$tab['DIV']]);
    }

    $newInterval = (int)($_REQUEST[Tabs::GROUPING_INTERVAL] ?? 0);
    if ($newInterval > 0) {
        $agentName = Agent::class . '::flush();';
        $dbAgent   = \CAgent::GetList([], ['MODULE_ID' => $mid, 'NAME' => $agentName])->Fetch();

        if ($dbAgent) {
            \CAgent::Update($dbAgent['ID'], ['AGENT_INTERVAL' => $newInterval]);
        }
    }
}

Message::showAdmin();

// update values
$arAllOptions = Tabs::getAllOptions();

$pendingErrorsCount = ErrorTable::getCount();
$pendingCountText   = $pendingErrorsCount > 0
    ? Loc::getMessage('rv-notifier__grouping_pending_count', ['#count#' => $pendingErrorsCount])
    : '';

$agentRow     = \CAgent::GetList([], ['MODULE_ID' => $mid, 'NAME' => Agent::class . '::flush();'])->Fetch();
$nextSendText = ($agentRow && $agentRow['NEXT_EXEC'])
    ? Loc::getMessage('rv-notifier__grouping_next_send', ['#datetime#' => $agentRow['NEXT_EXEC']])
    : '';

$smsTemplate = TemplateTable::getList([
    'filter' => ['=EVENT_NAME' => Notifier::SMS_EVENT_NAME],
    'select' => ['ACTIVE'],
    'limit'  => 1,
])->fetch();

$smsTemplateActive = $smsTemplate && ($smsTemplate['ACTIVE'] === true || $smsTemplate['ACTIVE'] === 'Y');

$setupHintParams = [
    '#event_name#'        => Notifier::EVENT_NAME,
    '#sms_event_name#'    => Notifier::SMS_EVENT_NAME,
    '#mail_template_url#' => '/bitrix/admin/message_admin.php?lang=' . LANGUAGE_ID .
        '&find_type_id=' . urlencode(Notifier::EVENT_NAME) . '&set_filter=Y',
    '#sms_template_url#'  => '/bitrix/admin/sms_template_admin.php?lang=' . LANGUAGE_ID .
        '&find_event_name_id=' . urlencode(Notifier::SMS_EVENT_NAME) . '&set_filter=Y',
];

$errorTableUrl = '/bitrix/admin/perfmon_table.php?lang=' . LANGUAGE_ID .
    '&table_name=' . urlencode(ErrorTable::getTableName());

$groupingHint = Loc::getMessage('rv-notifier__grouping-enabled_hint', [
    '#table_url#'  => $errorTableUrl,
    '#table_name#' => ErrorTable::getTableName(),
]);
?>
<form method="post" name="rover_notifier_options"
      action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($mid) ?>&amp;lang=<?= LANGUAGE_ID ?>"><?
    $tabControl = new CAdminTabControl("rover_notifier_Settings_tab", $aTabs);
    $tabControl->Begin();

    echo bitrix_sessid_post();

    foreach ($aTabs as $tab) {
        $tabControl->BeginNextTab();
        __AdmSettingsDrawList($mid, $arAllOptions[$tab['DIV']]);
        ?>
        <tr>
            <td colspan="2">
                <div class="adm-info-message-wrap">
                    <div class="adm-info-message">
                        <?= Loc::getMessage('rv-notifier__setup_hint_intro') ?>
                        <?= Loc::getMessage('rv-notifier__setup_hint_email', $setupHintParams) ?>
                        <?= Loc::getMessage(
                            $smsTemplateActive
                                ? 'rv-notifier__setup_hint_sms_ready'
                                : 'rv-notifier__setup_hint_sms_pending',
                            $setupHintParams
                        ) ?>
                        <?= Loc::getMessage('rv-notifier__setup_hint_outro') ?>
                    </div>
                </div>
            </td>
        </tr>
        <?
    }

    $tabControl->Buttons([]);
    $tabControl->End();
    ?>
</form>
<script>
    BX.ready(function () {
        var form              = document.forms['rover_notifier_options'];
        var enabledCheckbox   = BX('<?= Tabs::ENABLED ?>');
        var groupingCheckbox  = BX('<?= Tabs::GROUPING_ENABLED ?>');
        var intervalInput     = document.querySelector('input[name="<?= Tabs::GROUPING_INTERVAL ?>"]');
        var criticalCheckbox  = BX('<?= Tabs::CRITICAL_IMMEDIATE ?>');

        if (!form || !enabledCheckbox || !groupingCheckbox || !intervalInput || !criticalCheckbox) {
            return;
        }

        var toggle = function () {
            var moduleEnabled = enabledCheckbox.checked;

            groupingCheckbox.disabled = !moduleEnabled;
            intervalInput.disabled = !moduleEnabled || !groupingCheckbox.checked;
            criticalCheckbox.disabled = !moduleEnabled || !groupingCheckbox.checked;
        };

        // disabled-поле браузер не отправляет при сабмите, а Bitrix трактует отсутствие
        // чекбокса в запросе как явное "N" и затрёт сохранённое значение. Поэтому прямо
        // перед отправкой формы на мгновение снимаем disabled, чтобы реальное состояние
        // галочки всё равно ушло в запрос
        form.addEventListener('submit', function () {
            groupingCheckbox.disabled = false;
            criticalCheckbox.disabled = false;
        });

        enabledCheckbox.addEventListener('change', toggle);
        groupingCheckbox.addEventListener('change', toggle);
        toggle();

        BX.hint_replace(
            BX('<?= Tabs::GROUPING_ENABLED ?>_hint'),
            '<?= CUtil::JSEscape($groupingHint) ?>'
        );

        // счётчик очереди вешаем на тот же td, где сам чекбокс, - справа от него, ссылкой на таблицу
        var pendingCountText = '<?= CUtil::JSEscape($pendingCountText) ?>';
        if (pendingCountText) {
            var pendingLink = document.createElement('a');
            pendingLink.href = '<?= CUtil::JSEscape($errorTableUrl) ?>';
            pendingLink.target = '_blank';
            pendingLink.style.cssText = 'color:#828282;white-space:nowrap;';
            pendingLink.textContent = pendingCountText;
            groupingCheckbox.parentNode.appendChild(pendingLink);
        }

        // и дату следующей отправки - на тот же td, где поле интервала, справа от него
        var nextSendText = '<?= CUtil::JSEscape($nextSendText) ?>';
        if (nextSendText) {
            var nextSendSpan = document.createElement('span');
            nextSendSpan.style.cssText = 'color:#828282;white-space:nowrap;';
            nextSendSpan.textContent = nextSendText;
            intervalInput.parentNode.appendChild(nextSendSpan);
        }
    });
</script>
