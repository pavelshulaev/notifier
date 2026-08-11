<?php

$MESS['rv-notifier__module-not-found'] = 'Module "#mid#" not found';

$MESS['rv-notifier__grouping_pending_count'] = 'queued to send: #count#';

$MESS['rv-notifier__grouping_next_send'] = 'next send: #datetime#';

$MESS['rv-notifier__grouping-enabled_hint'] =
    'Until accumulated errors are sent, their data is stored in the internal table ' .
    '<a href="#table_url#" target="_blank">#table_name#</a>. Rows are removed once a group is sent.';

$MESS['rv-notifier__setup_hint_intro'] =
    'This module does not send anything itself — it only triggers standard Bitrix mail and SMS events. ' .
    'Delivery is handled by integrations already configured on the site: ' .
    '<ul>';

$MESS['rv-notifier__setup_hint_email'] =
    '<li><b>Email</b> — a <a href="#mail_template_url#" target="_blank">mail event template for ' .
    '"#event_name#"</a> was created automatically on install, edit the recipient and text there if needed;</li>';

$MESS['rv-notifier__setup_hint_sms_pending'] =
    '<li><b>Telegram / WhatsApp / MAX / SMS</b> — an ' .
    '<a href="#sms_template_url#" target="_blank">SMS template for the "#sms_event_name#" event</a> ' .
    'was also created automatically, but it is disabled and waiting for a recipient: install the ' .
    '"Message Service" (messageservice) module and any suitable sender connector, then open the template, ' .
    'replace "+7XXXXXXXXXX" with the real phone number/chat ID, and enable it;</li>';

$MESS['rv-notifier__setup_hint_sms_ready'] =
    '<li><b>Telegram / WhatsApp / MAX / SMS</b> — an ' .
    '<a href="#sms_template_url#" target="_blank">SMS template for the "#sms_event_name#" event</a> ' .
    'was created and is enabled; for actual delivery, the "Message Service" (messageservice) module ' .
    'and a suitable sender connector must be installed.</li>';

$MESS['rv-notifier__setup_hint_outro'] = '</ul>';
