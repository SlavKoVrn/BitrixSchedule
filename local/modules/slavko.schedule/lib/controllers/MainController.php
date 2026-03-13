<?php
namespace Slavko\Schedule\Controllers;

use Bitrix\Main\Engine\Controller;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Error;

class MainController extends Controller
{
    public function getRoomsAction($q)
    {
        if (!Loader::includeModule('iblock')) {
            $this->errorCollection[] = new Error('Iblock module not available');
            return ['values' => []];
        }

        // Get rooms iblock ID from saved options
        $roomsIblockId = (int)\COption::GetOptionString("slavko.schedule", "rooms_iblock_id", 0);
        
        if ($roomsIblockId <= 0) {
            $this->errorCollection[] = new Error('Rooms iblock ID not configured');
            return ['values' => []];
        }

        $result = [];
        try {
            $filter = [
                'IBLOCK_ID' => $roomsIblockId,
                'ACTIVE' => 'Y',
            ];

            if (!empty($q)) {
                $filter['%NAME'] = $q;
            }

            $query = ElementTable::query()
                ->setSelect(['ID', 'NAME'])
                ->setFilter($filter)
                ->setOrder(['NAME' => 'ASC'])
                ->setLimit(20);

            $res = $query->exec();
            while ($element = $res->fetch()) {
                $result[] = [
                    'id' => (int)$element['ID'],
                    'name' => $element['NAME']
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->errorCollection[] = new Error('Database error: ' . $e->getMessage());
            return ['values' => []];
        }
    }
}