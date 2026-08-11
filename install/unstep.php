<?php use Bitrix\Main\Localization\Loc; ?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="rover.notifier">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <?= CAdminMessage::ShowMessage(Loc::getMessage("MOD_UNINST_WARN")) ?>
    <p>
        <input type="checkbox" name="keep_events" id="keep_events" value="Y" checked>
        <label for="keep_events"><?= Loc::getMessage("rv-notifier__uninstall-keep-events") ?></label>
    </p>
    <input type="submit" name="inst" value="<?= Loc::getMessage("MOD_UNINST_DEL") ?>">
</form>
