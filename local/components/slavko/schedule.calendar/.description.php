<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME' => 'Календарь расписания сотрудника',
    'DESCRIPTION' => 'Отображение расписания сотрудника в виде календаря (FullCalendar)',
    'PATH' => [
        'ID' => 'slavko_components',
        'NAME' => 'Компоненты Slavko',
    ],
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
        'INIT_VIEW' => [
            'NAME' => 'Начальный вид',
            'TYPE' => 'LIST',
            'VALUES' => [
                'dayGridMonth' => 'Месяц',
                'timeGridWeek' => 'Неделя',
                'timeGridDay' => 'День',
                'listMonth' => 'Список',
            ],
            'DEFAULT' => 'dayGridMonth',
        ],
        'FIRST_HOUR' => [
            'NAME' => 'Первый час дня',
            'TYPE' => 'STRING',
            'DEFAULT' => '8',
        ],
        'LAST_HOUR' => [
            'NAME' => 'Последний час дня',
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
    ],
];
