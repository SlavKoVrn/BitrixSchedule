<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Application;

class SchedulerComponent extends \CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['WORKER_ID'] = (int)($arParams['WORKER_ID'] ?: $_REQUEST['worker_id'] ?? 0);
        $arParams['YEAR'] = (int)($arParams['YEAR'] ?: date('Y'));
        return $arParams;
    }

    public function executeComponent()
    {
        global $DB;
        
        if (!$this->arParams['WORKER_ID']) {
            ShowError('Не указан ID сотрудника');
            return;
        }

        // Load schedule from DB
        $sql = "SELECT SCHEDULE FROM sk_schedule WHERE WORKER_ID = " . $DB->ForSql($this->arParams['WORKER_ID']);
        $res = $DB->Query($sql, false);
        $scheduleData = [];
        
        if ($row = $res->Fetch()) {
            $scheduleData = json_decode($row['SCHEDULE'], true) ?: [];
        }


        $this->arResult['ROOMS'] = $this->extractRooms($scheduleData);
        $this->arResult['PARAMS'] = $this->arParams;

        $this->includeComponentTemplate();
    }

    private function extractRooms($scheduleData)
    {
        $rooms = [];
        // Optional: define colors for events (cycle through them)
        $colors = ['orange', 'red', 'green', 'black', 'blue', 'purple', 'teal'];
        $colorIndex = 0;
        
        foreach ($scheduleData as $roomId => $roomData) {
            // Skip non-array entries and metadata keys
            if ($roomId === 'roomName' || !is_array($roomData)) {
                continue;
            }
            
            if (!empty($roomData['roomName'])) {
                $rooms[] = [
                    'id' => $roomId,                           // Resource ID (string or int)
                    'title' => $roomData['roomName'],          // Display name
                    'eventColor' => $colors[$colorIndex % count($colors)], // Optional color
                ];
                $colorIndex++;
            }
        }
        
        return $rooms;
    }
}
