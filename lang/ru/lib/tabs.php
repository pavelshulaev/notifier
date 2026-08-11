<?php

use Rover\Notifier\Tabs;

$MESS['rv-notifier__' . Tabs::TAB__MAIN . '_label'] = 'Настройки';
$MESS['rv-notifier__' . Tabs::TAB__MAIN . '_descr'] = 'Настройки';

$MESS['rv-notifier__' . Tabs::ENABLED . '_label']           = 'Перехват ошибок включён';
$MESS['rv-notifier__' . Tabs::GROUPING_ENABLED . '_label']  = 'Группировать одинаковые ошибки';
$MESS['rv-notifier__' . Tabs::GROUPING_INTERVAL . '_label'] =
    'Интервал отправки группы, сек (например, 3600 = раз в час)';
$MESS['rv-notifier__' . Tabs::CRITICAL_IMMEDIATE . '_label'] =
    'Критичные ошибки (Fatal error / необработанное исключение) отправлять сразу, минуя группировку';
