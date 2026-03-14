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
        $year = (int)date('Y');

        $rules = json_decode($scheduleJson, true);
        if (!is_array($rules)) {
            return;
        }

        $expandedSchedule = self::expandScheduleToYearByRoom($rules, $year);

        $DB->StartTransaction();
        try {
            $scheduleEscaped = $DB->ForSql(json_encode($expandedSchedule, JSON_UNESCAPED_UNICODE));
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

    private static function expandScheduleToYearByRoom($rules, $year)
    {
        $scheduleByRoom = [];
        
        $workingDays = [];
        $specialDays = [];
        $weekendDays = [];

        foreach ($rules as $rule) {
            $roomId = $rule['roomId'] ?? 0;
            if (!isset($scheduleByRoom[$roomId])) {
                $scheduleByRoom[$roomId] = [
                    'roomName' => $rule['roomName'] ?? 'Кабинет #' . $roomId
                ];
            }
            
            if ($rule['type'] === 'working' && isset($rule['weekday'])) {
                if (!isset($workingDays[$roomId])) {
                    $workingDays[$roomId] = [];
                }
                $workingDays[$roomId][$rule['weekday']] = [
                    'start' => $rule['start'] ?? null,
                    'end' => $rule['end'] ?? null
                ];
            } elseif ($rule['type'] === 'special' && !empty($rule['date'])) {
                if (!isset($specialDays[$roomId])) {
                    $specialDays[$roomId] = [];
                }
                $specialDays[$roomId][$rule['date']] = [
                    'start' => $rule['start'] ?? null,
                    'end' => $rule['end'] ?? null
                ];
            } elseif ($rule['type'] === 'weekend' && !empty($rule['date'])) {
                if (!isset($weekendDays[$roomId])) {
                    $weekendDays[$roomId] = [];
                }
                $weekendDays[$roomId][$rule['date']] = true;
            }
        }

        $startDate = new DateTime("$year-01-01");
        $endDate = new DateTime("$year-12-31");

        foreach ($scheduleByRoom as $roomId => &$roomData) {
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayOfWeek = (int)$currentDate->format('N');
                $dayOfWeekConverted = ($dayOfWeek === 7) ? 0 : $dayOfWeek;

                $daySchedule = [
                    'type' => 'working',
                    'weekday' => $dayOfWeekConverted,
                    'start' => null,
                    'end' => null,
                    'source' => 'default'
                ];

                if (isset($workingDays[$roomId][$dayOfWeekConverted])) {
                    $workRule = $workingDays[$roomId][$dayOfWeekConverted];
                    $daySchedule = [
                        'type' => 'working',
                        'weekday' => $dayOfWeekConverted,
                        'start' => $workRule['start'],
                        'end' => $workRule['end'],
                        'source' => 'working'
                    ];
                }

                if (isset($specialDays[$roomId][$dateStr])) {
                    $specialRule = $specialDays[$roomId][$dateStr];
                    $daySchedule = [
                        'type' => 'special',
                        'weekday' => $dayOfWeekConverted,
                        'start' => $specialRule['start'],
                        'end' => $specialRule['end'],
                        'source' => 'special'
                    ];
                }

                if (isset($weekendDays[$roomId][$dateStr])) {
                    $daySchedule = [
                        'type' => 'weekend',
                        'weekday' => $dayOfWeekConverted,
                        'start' => null,
                        'end' => null,
                        'source' => 'weekend'
                    ];
                }

                $roomData[$dateStr] = $daySchedule;
                $currentDate->modify('+1 day');
            }
        }

        return $scheduleByRoom;
    }
}
