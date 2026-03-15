<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$action = $_REQUEST['action'] ?? '';
global $DB;

header('Content-Type: application/json');

if ($action === 'getEvents') {
    $start = $_POST['start'] ?? null;
    $end = $_POST['end'] ?? null;
    $workerId = (int)($_REQUEST['worker_id'] ?? 0);
    
    $result = [];
    
    try {
        $rangeStart = $start ? new \DateTime($start) : null;
        $rangeEnd = $end ? new \DateTime($end) : null;
        
        $sql = "SELECT SCHEDULE FROM sk_schedule WHERE WORKER_ID = " . $workerId;
        $res = $DB->Query($sql, false);
        
        if ($row = $res->Fetch()) {
            $scheduleRaw = json_decode($row['SCHEDULE'], true);
            
            if (is_array($scheduleRaw)) {
                foreach ($scheduleRaw as $roomId => $roomData) {
                    
                    if (!is_numeric($roomId) || !is_array($roomData)) {
                        continue;
                    }

                    $roomName = $roomData['roomName'] ?? 'Room ' . $roomId;

                    // 5. Iterate through Dates within the Room
                    foreach ($roomData as $dateKey => $dayInfo) {
                        // Skip metadata keys like 'roomName'
                        if ($dateKey === 'roomName') {
                            continue;
                        }

                        // Ensure key is a valid date format (YYYY-MM-DD)
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                            continue;
                        }

                        // 6. Filter by Requested Range (Performance Optimization)
                        $eventDate = new \DateTime($dateKey);
                        
                        if ($rangeStart && $eventDate < $rangeStart) {
                            continue;
                        }
                        if ($rangeEnd && $eventDate > $rangeEnd) {
                            continue;
                        }

                        // 7. Extract Times
                        // Default to 09:00-18:00 if not specified (e.g. weekends might lack times)
                        $startTime = $dayInfo['start'] ?? '09:00';
                        $endTime = $dayInfo['end'] ?? '18:00';
                        $type = $dayInfo['type'] ?? 'working';

                        // 8. Build Event Object
                        $result[] = [
                            'id' => $dateKey . '_' . $roomId,
                            'resourceId' => $roomId,
                            'title' => date('d.m.Y',strtotime($dateKey)) . ' ' . $startTime.'-'. $endTime.' '.$roomName,
                            'start' => $dateKey . ' ' . $startTime,
                            'end' => $dateKey . ' ' . $endTime,
                            'backgroundColor' => ($type === 'weekend') ? '#FF5722' : (($type === 'special') ? '#FFC107' : '#4CAF50'),
                        ];
                    }
                }
            }
        }

        echo \Bitrix\Main\Web\Json::encode($result);
        
    } catch (\Exception $e) {
        // Log error in Bitrix log for debugging
        \Bitrix\Main\Diag\ExceptionHandlerFormatter::log($e);
        echo \Bitrix\Main\Web\Json::encode(['error' => $e->getMessage()]);
    }
}
die();