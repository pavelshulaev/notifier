<?php

$MESS['rv-notifier__module-not-found'] = 'Модуль «#mid#» не найден';

$MESS['rv-notifier__grouping_pending_count'] = 'в очереди: #count#';

$MESS['rv-notifier__grouping_next_send'] = 'следующая отправка: #datetime#';

$MESS['rv-notifier__grouping-enabled_hint'] =
    'Пока накопленные ошибки не отправлены, информация о них хранится в служебной таблице ' .
    '<a href="#table_url#" target="_blank">#table_name#</a>. После отправки группы строки удаляются.';

$MESS['rv-notifier__setup_hint_intro'] =
    'Модуль сам ничего не отправляет — он только дёргает <b>почтовое и SMS события</b>. ' .
    'Доставку выполняют уже настроенные на сайте интеграции: ' .
    '<ul>';

$MESS['rv-notifier__setup_hint_email'] =
    '<li><b>Email</b> — при установке модуля автоматически создан ' .
    '<a href="#mail_template_url#" target="_blank">шаблон почтового события «#event_name#»</a>, ' .
    'отредактируйте в нём получателя и текст письма при необходимости;</li>';

$MESS['rv-notifier__setup_hint_sms_pending'] =
    '<li><b>Telegram / WhatsApp / MAX / SMS</b> — при установке модуля также автоматически создан ' .
    '<a href="#sms_template_url#" target="_blank">SMS-шаблон для события «#sms_event_name#»</a>, ' .
    'но он выключен и ждёт получателя: ' .
    'установите модуль «Сервис уведомлений» (messageservice) и любой подходящий коннектор-отправитель, ' .
    'затем откройте шаблон, укажите вместо «+7XXXXXXXXXX» реальный номер/chat_id и включите его;</li>';

$MESS['rv-notifier__setup_hint_sms_ready'] =
    '<li><b>Telegram / WhatsApp / MAX / SMS</b> — создан и включён ' .
    '<a href="#sms_template_url#" target="_blank">SMS-шаблон для события «#sms_event_name#»</a>; ' .
    'для реальной доставки должен быть установлен модуль «Сервис уведомлений» (messageservice) ' .
    'и подходящий коннектор-отправитель.</li>';

$MESS['rv-notifier__setup_hint_outro'] = '</ul>';
