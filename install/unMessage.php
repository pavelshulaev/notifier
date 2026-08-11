<?php

use Bitrix\Main\Localization\Loc;

global $APPLICATION, $rnErrors;

if (empty($rnErrors)) {
    CAdminMessage::ShowNote(Loc::getMessage("MOD_UNINST_OK"));
} else {
    CAdminMessage::ShowMessage(
        [
            "TYPE"    => "ERROR",
            "MESSAGE" => Loc::getMessage("MOD_UNINST_ERR"),
            "DETAILS" => implode("<br/>", $rnErrors),
            "HTML"    => true
        ]);
}

?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="submit" name="" value="<?= Loc::getMessage("MOD_BACK") ?>">
</form>
