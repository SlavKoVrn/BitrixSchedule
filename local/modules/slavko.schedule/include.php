<?php
include_once($_SERVER['DOCUMENT_ROOT'] . "/local/modules/slavko.schedule/tabset.php");
include_once($_SERVER['DOCUMENT_ROOT'] . "/local/modules/slavko.schedule/lib/controllers/MainController.php");

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

AddEventHandler('main', 'OnAdminIBlockElementEdit', function () {
    $tabset = new ScheduleTabset();
    return [
        'TABSET'  => 'shedule_tabset',
        'Check'   => [$tabset, 'check'],
        'Action'  => [$tabset, 'action'],
        'GetTabs' => [$tabset, 'getTabList'],
        'ShowTab' => [$tabset, 'showTabContent'],
    ];
});

AddEventHandler("iblock", "OnAfterIBlockElementAdd", ["IblockElementSave", "OnAfterElementAdd"]);
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", ["IblockElementSave", "OnAfterElementUpdate"]);

class IblockElementSave
{
    public static function OnAfterElementAdd($arFields)
    {
        if ((int)$arFields['ID'] > 0 && empty($arFields['RESULT']) && $arFields['RESULT'] !== false) {
            self::SaveToCustomTable($arFields['ID'], $arFields['IBLOCK_ID']);
        }
    }

    public static function OnAfterElementUpdate($arFields)
    {
        if ((int)$arFields['ID'] > 0) {
            self::SaveToCustomTable($arFields['ID'], $arFields['IBLOCK_ID']);
        }
    }

    private static function SaveToCustomTable($elementId, $iblockId)
    {
        global $DB;
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $scheduleJson = $request->getPost('SLAVKO_SCHEDULE_DATA');

        if (empty($scheduleJson)) return;

        $configuredWorkerIblockId = (int)COption::GetOptionString("slavko.schedule", "worker_iblock_id", 0);
        if ($configuredWorkerIblockId > 0 && $iblockId != $configuredWorkerIblockId) {
            return;
        }

        $workerId = (int)$elementId;
        $DB->StartTransaction();
        try {
            $scheduleEscaped = $DB->ForSql($scheduleJson);
            $sql = "
                INSERT INTO sk_schedule (`WORKER_ID`, `SCHEDULE`)
                VALUES ({$workerId}, '{$scheduleEscaped}')
                ON DUPLICATE KEY UPDATE
                `SCHEDULE` = '{$scheduleEscaped}'
            ";
            $DB->Query($sql, true, "Error saving schedule for worker {$workerId}");
            $DB->Commit();
        } catch (Exception $e) {
            $DB->Rollback();
        }
    }
}