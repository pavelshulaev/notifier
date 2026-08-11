<?php

use Rover\Notifier\Tabs;

$MESS['rv-notifier__' . Tabs::TAB__MAIN . '_label'] = 'Settings';
$MESS['rv-notifier__' . Tabs::TAB__MAIN . '_descr'] = 'Settings';

$MESS['rv-notifier__' . Tabs::ENABLED . '_label']           = 'Error catching enabled';
$MESS['rv-notifier__' . Tabs::GROUPING_ENABLED . '_label']  = 'Group identical errors';
$MESS['rv-notifier__' . Tabs::GROUPING_INTERVAL . '_label'] =
    'Group flush interval, seconds (e.g. 3600 = once an hour)';
$MESS['rv-notifier__' . Tabs::CRITICAL_IMMEDIATE . '_label'] =
    'Send critical errors (fatal error / unhandled exception) immediately, bypassing grouping';
