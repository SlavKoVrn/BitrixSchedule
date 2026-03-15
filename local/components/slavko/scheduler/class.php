<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;

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
        $this->arResult['WORKER_NAME'] = $this->getWorkerName();

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

    private function getWorkerName()
    {
        $workerName = '';

        // Get IBLOCK_ID from options
        $workerIblockId = (int)COption::GetOptionString("slavko.schedule", "worker_iblock_id", 0);

        // Get Element ID from params
        $workerId = (int)$this->arParams['WORKER_ID'];

        // Validate data
        if ($workerIblockId > 0 && $workerId > 0) {
            try {
                // D7 ORM Query
                $element = ElementTable::getRow([
                    'filter' => [
                        '=ID' => $workerId,
                        '=IBLOCK_ID' => $workerIblockId,
                        '=ACTIVE' => true // Only get active elements
                    ],
                    'select' => ['NAME']
                ]);

                if (!empty($element['NAME'])) {
                    $workerName = $element['NAME'];
                }
            } catch (ArgumentException $e) {
                // Handle case where IBLOCK_ID is incorrect or table doesn't exist
                // Log error if needed
            }
        }

        return $workerName;
    }
}
