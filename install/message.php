<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/module_admin.php');

global $APPLICATION, $rnErrors;

if (empty($rnErrors)):
    CAdminMessage::ShowNote(Loc::getMessage("MOD_INST_OK"));

    ?><form action="/bitrix/admin/settings.php">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="mid" value="rover.notifier">
    <input type="submit" name="" value="<?= Loc::getMessage("rv-notifier__finish") ?>">
    </form><?php
else:
    CAdminMessage::ShowMessage(
        [
            "TYPE"    => "ERROR",
            "MESSAGE" => Loc::getMessage("MOD_INST_ERR"),
            "DETAILS" => implode("<br/>", $rnErrors),
            "HTML"    => true
        ]);

    ?>
    <form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="submit" name="" value="<?= Loc::getMessage("MOD_BACK") ?>">
    </form><?php
endif;
