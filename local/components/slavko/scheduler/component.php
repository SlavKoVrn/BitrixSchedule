<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

if (!\Bitrix\Main\Loader::includeModule('slavko.schedule')) {
    ShowError('Модуль slavko.schedule не установлен');
    return;
}

$this->componentClass = '\SchedulerComponent';
