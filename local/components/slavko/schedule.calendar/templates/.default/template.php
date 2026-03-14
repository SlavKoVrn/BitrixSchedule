<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/** @var array $arResult */
global $templateFolder;
?>

<div id="schedule-calendar" class="schedule-calendar" 
     data-worker-id="<?= (int)$arResult['PARAMS']['WORKER_ID'] ?>"
     data-year="<?= (int)$arResult['PARAMS']['YEAR'] ?>">
    
    <div class="schedule-calendar-header">
        <div class="schedule-legend">
            <span class="legend-item"><span class="legend-color working"></span> Рабочий день</span>
            <span class="legend-item"><span class="legend-color special"></span> Особый день</span>
            <span class="legend-item"><span class="legend-color weekend"></span> Выходной</span>
        </div>
    </div>
    
    <div id="calendar"></div>
    
    <div id="event-modal" class="schedule-modal" style="display:none;">
        <div class="schedule-modal-content">
            <span class="schedule-modal-close">&times;</span>
            <h4 id="modal-title"></h4>
            <div id="modal-details"></div>
        </div>
    </div>
</div>

<script>
window.SCHEDULE_CALENDAR_DATA = <?= \Bitrix\Main\Web\Json::encode($arResult['EVENTS']) ?>;
window.SCHEDULE_ROOMS = <?= \Bitrix\Main\Web\Json::encode($arResult['ROOMS']) ?>;
</script>

<?php
// Add FullCalendar CSS/JS from CDN or local
$APPLICATION->SetAdditionalCSS('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css');
$APPLICATION->AddHeadScript('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], false, true);
$APPLICATION->AddHeadScript($templateFolder . '/script.js', [], false, true);
$APPLICATION->SetAdditionalCSS($templateFolder . '/style.css');
