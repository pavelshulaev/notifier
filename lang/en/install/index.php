<?php

$MESS["rv-notifier__MODULE_NAME"]  = "Error notifications by email, SMS and messengers";
$MESS["rv-notifier__MODULE_DESC"]  =
    "Catches site errors and notifies the administrator by email, SMS or messenger - " .
    "via Bitrix's standard mail and SMS events, no coding required.";
$MESS["rv-notifier__PARTNER_NAME"] = "Pavel Shulaev";
$MESS["rv-notifier__PARTNER_URI"]  = "https://rover-it.ru";

$MESS['rv-notifier__install_title']      = "Installing module \"#name#\"";
$MESS['rv-notifier__uninstall_title']    = "Uninstalling module \"#name#\"";
$MESS['rv-notifier__version_info_error'] = "Version information not found";
$MESS['rv-notifier__finish']             = "Go to module settings";

$MESS['rv-notifier__uninstall-keep-events'] =
    'Keep the module\'s mail and SMS events and templates (created on install and configured by you)';

$MESS['rv-notifier__event_type_name_mail']        = "Site error, email (rover.notifier)";
$MESS['rv-notifier__event_type_description_mail'] =
    "Mail event for a site error caught by rover.notifier";

$MESS['rv-notifier__event_type_name_sms']        = "Site error, SMS/messengers (rover.notifier)";
$MESS['rv-notifier__event_type_description_sms'] =
    "SMS event for a site error caught by rover.notifier - use it for Telegram/WhatsApp/MAX/SMS";
