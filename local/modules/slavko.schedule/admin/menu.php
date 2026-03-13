<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
return [
    "parent_menu" => "global_menu_settings",
    "section" => "slavko.schedule",
    "sort" => 100,
    "text" => "Расписание сотрудников",
    "title" => "Расписание сотрудников",
    "items" => [
        [
            "text" => "Настройка расписания сотрудников",
            "url" => "schedule_admin.php",
            "more_url" => ["schedule_admin.php"],
            "title" => "Установите информационный блок сотрудников"
        ]
    ]
];