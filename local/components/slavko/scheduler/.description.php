<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME' => 'Календарь расписания сотрудника',
    'DESCRIPTION' => 'Отображение расписания сотрудника в виде календаря (FullCalendar)',
    'PARAMETERS' => [
        'WORKER_ID' => [
            'NAME' => 'ID сотрудника',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'MULTIPLE' => false,
        ],
        'YEAR' => [
            'NAME' => 'Год',
            'TYPE' => 'STRING',
            'DEFAULT' => date('Y'),
            'MULTIPLE' => false,
        ],
    ],
];
