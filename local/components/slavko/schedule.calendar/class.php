<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Application;

class ScheduleCalendarComponent extends \CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['WORKER_ID'] = (int)($arParams['WORKER_ID'] ?: $_REQUEST['worker_id'] ?? 0);
        $arParams['YEAR'] = (int)($arParams['YEAR'] ?: date('Y'));
        //$arParams['INIT_VIEW'] = $arParams['INIT_VIEW'] ?: 'dayGridMonth';
        //$arParams['FIRST_HOUR'] = (int)($arParams['FIRST_HOUR'] ?: 8);
        //$arParams['LAST_HOUR'] = (int)($arParams['LAST_HOUR'] ?: 20);
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

        // Prepare events for FullCalendar
        $this->arResult['EVENTS'] = $this->prepareCalendarEvents($scheduleData, $this->arParams['YEAR']);
        $this->arResult['ROOMS'] = $this->extractRooms($scheduleData);
        $this->arResult['PARAMS'] = $this->arParams;

        $this->includeComponentTemplate();
    }

    private function prepareCalendarEvents($scheduleData, $year)
    {
        $events = [];
        
        foreach ($scheduleData as $roomId => $roomData) {
            if ($roomId === 'roomName' || !is_array($roomData)) continue;
            
            $roomName = $roomData['roomName'] ?? 'Кабинет #' . $roomId;
            
            foreach ($roomData as $dateStr => $dayData) {
                if ($dateStr === 'roomName' || !is_array($dayData)) continue;
                
                $event = [
                    'title' => $this->getEventTitle($dayData, $roomName),
                    'start' => $dateStr,
                    'allDay' => true,
                    'extendedProps' => [
                        'type' => $dayData['type'] ?? 'working',
                        'weekday' => $dayData['weekday'] ?? null,
                        'roomId' => $roomId,
                        'roomName' => $roomName,
                    ],
                    'classNames' => ['fc-event-' . ($dayData['type'] ?? 'working')],
                ];
                
                // Add time for working/special days
                if (!empty($dayData['start']) && !empty($dayData['end'])) {
                    $event['start'] = $dateStr . 'T' . $dayData['start'];
                    $event['end'] = $dateStr . 'T' . $dayData['end'];
                    $event['allDay'] = false;
                }
                
                $events[] = $event;
            }
        }
        
        return $events;
    }

    private function getEventTitle($dayData, $roomName)
    {
        $type = $dayData['type'] ?? 'working';
        
        if ($type === 'weekend') {
            return '🔴 Выходной';
        }
        
        if ($type === 'special') {
            $time = '';
            if (!empty($dayData['start']) && !empty($dayData['end'])) {
                $time = ' ' . $dayData['start'] . '–' . $dayData['end'];
            }
            return '🟡 Особый день' . $time;
        }
        
        // Working day
        $time = '';
        if (!empty($dayData['start']) && !empty($dayData['end'])) {
            $time = $dayData['start'] . '–' . $dayData['end'];
        } else {
            $time = 'без часов';
        }
        return '🟢 ' . $roomName . ' (' . $time . ')';
    }

    private function extractRooms($scheduleData)
    {
        $rooms = [];
        foreach ($scheduleData as $roomId => $roomData) {
            if ($roomId === 'roomName' || !is_array($roomData)) continue;
            if (!empty($roomData['roomName'])) {
                $rooms[$roomId] = $roomData['roomName'];
            }
        }
        return $rooms;
    }
}
