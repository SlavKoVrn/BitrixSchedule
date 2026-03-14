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
        $allRoomIds = [];
        $roomNames = [];
        
        $workingDays = [];
        $specialDays = [];
        $weekendDates = [];

        // First pass: collect all VALID room IDs (roomId > 0) and their names
        foreach ($rules as $rule) {
            $roomId = $rule['roomId'] ?? 0;
            // Only collect rooms with valid ID (> 0)
            if ($roomId > 0 && !isset($allRoomIds[$roomId])) {
                $allRoomIds[$roomId] = true;
                $roomNames[$roomId] = $rule['roomName'] ?? 'Кабинет #' . $roomId;
            }
        }

        // Second pass: organize rules by room and type
        foreach ($rules as $rule) {
            $roomId = $rule['roomId'] ?? 0;
            
            // Skip rules without valid room (except weekends which apply to all rooms)
            if ($roomId <= 0 && $rule['type'] !== 'weekend') {
                continue;
            }
            
            // For weekends, just collect the date (will apply to all valid rooms)
            if ($rule['type'] === 'weekend' && !empty($rule['date'])) {
                if (!in_array($rule['date'], $weekendDates)) {
                    $weekendDates[] = $rule['date'];
                }
                continue;
            }
            
            // Skip if room doesn't exist
            if (!isset($allRoomIds[$roomId])) {
                continue;
            }
            
            if (!isset($scheduleByRoom[$roomId])) {
                $scheduleByRoom[$roomId] = [
                    'roomName' => $roomNames[$roomId] ?? 'Кабинет #' . $roomId
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
            }
        }

        $startDate = new DateTime("$year-01-01");
        $endDate = new DateTime("$year-12-31");

        // Generate schedule for each VALID room only
        foreach ($scheduleByRoom as $roomId => &$roomData) {
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayOfWeek = (int)$currentDate->format('N');
                $dayOfWeekConverted = ($dayOfWeek === 7) ? 0 : $dayOfWeek;

                $hasWorkingRule = isset($workingDays[$roomId][$dayOfWeekConverted]);
                $hasSpecialRule = isset($specialDays[$roomId][$dateStr]);
                $hasWeekendRule = in_array($dateStr, $weekendDates);

                // Skip days without any specific schedule
                if (!$hasSpecialRule && !$hasWeekendRule) {
                    if ($hasWorkingRule) {
                        $workRule = $workingDays[$roomId][$dayOfWeekConverted];
                        if (empty($workRule['start']) || empty($workRule['end'])) {
                            $currentDate->modify('+1 day');
                            continue;
                        }
                    } else {
                        $currentDate->modify('+1 day');
                        continue;
                    }
                }

                // Build day schedule - only include start/end if they have values
                if ($hasWeekendRule) {
                    $roomData[$dateStr] = [
                        'type' => 'weekend',
                        'weekday' => $dayOfWeekConverted
                    ];
                } elseif ($hasSpecialRule) {
                    $specialRule = $specialDays[$roomId][$dateStr];
                    $daySchedule = [
                        'type' => 'special',
                        'weekday' => $dayOfWeekConverted
                    ];
                    if (!empty($specialRule['start'])) {
                        $daySchedule['start'] = $specialRule['start'];
                    }
                    if (!empty($specialRule['end'])) {
                        $daySchedule['end'] = $specialRule['end'];
                    }
                    $roomData[$dateStr] = $daySchedule;
                } elseif ($hasWorkingRule) {
                    $workRule = $workingDays[$roomId][$dayOfWeekConverted];
                    $daySchedule = [
                        'type' => 'working',
                        'weekday' => $dayOfWeekConverted
                    ];
                    if (!empty($workRule['start'])) {
                        $daySchedule['start'] = $workRule['start'];
                    }
                    if (!empty($workRule['end'])) {
                        $daySchedule['end'] = $workRule['end'];
                    }
                    $roomData[$dateStr] = $daySchedule;
                }

                $currentDate->modify('+1 day');
            }
        }

        return $scheduleByRoom;
    }
}