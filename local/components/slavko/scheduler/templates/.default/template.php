<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$asset = \Bitrix\Main\Page\Asset::getInstance();
$componentPath = $this->__component->GetPath();
$templatePath = $componentPath . '/templates/.default';

// CSS
$asset->addCss($templatePath . '/css/fullcalendar.min.css');
$asset->addCss($templatePath . '/css/scheduler.min.css');

// JS - load in correct order
$asset->addJs($templatePath . '/js/jquery.min.js');
$asset->addJs($templatePath . '/js/moment-with-locales.js');
$asset->addJs($templatePath . '/js/fullcalendar.min.js');
$asset->addJs($templatePath . '/js/scheduler.min.js');
$asset->addJs($templatePath . '/js/ru.js');
$asset->addJs($templatePath . '/js/analytics.js', ['defer' => true]);
?>

<div id="scheduler" class="schedule-calendar">
    <div id="calendar"></div>
</div>

<script>
$(document).ready(function() {
    
    var global_calendar = $('#calendar');
    
    global_calendar.fullCalendar({
        tagName: 'div',
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
        resourceAreaWidth: 250,
        editable: true,
        aspectRatio: 1.5,
        scrollTime: '10:00',
        header: {
            left: 'promptResource today prev,next',
            center: 'title',
            right: 'timelineDay,timelineThreeDays,agendaWeek,month'
        },
        defaultView: 'timelineDay',
        views: {
            timelineThreeDays: {
                type: 'timeline',
                duration: { days: 3 }
            }
        },
        resourceLabelText: 'Номер на час',
        resources: <?= \Bitrix\Main\Web\Json::encode($arResult['ROOMS']) ?>,
        events: {
            url: '/local/components/slavko/scheduler/ajax.php?action=getEvents&worker_id=<?= $arParams['WORKER_ID'] ?>',
            type: 'POST',
            error: function() {
                alert('Ошибка соединения с источником данных!');
            }
        },
        timeFormat: 'H:mm',
        dayClick: function(date, allDay, jsEvent, view) {
            $.ajax({
                url: '/scheduler/default/format-date',
                type: "POST",
                data: {'date': date.toString()},
                success: function(data) {
                    var obj = JSON.parse(data);
                    if (typeof current_date !== 'undefined' && current_date == 1) {
                        $('#date_from').val(obj.date);
                        $('#hour_from').val(obj.hour);
                        $('#minute_from').val(obj.minute);
                    } else {
                        $('#date_to').val(obj.date);
                        $('#hour_to').val(obj.hour);
                        $('#minute_to').val(obj.minute);
                    }
                }
            });
        },
        eventClick: function(calEvent, jsEvent, view) {
            if (typeof event_id !== 'undefined') event_id.val(calEvent.id);
            if (typeof event_type !== 'undefined') event_type.val(calEvent.title);
            
            $.ajax({
                url: '/scheduler/default/look-event',
                type: "POST",
                data: {'eventId': calEvent.id},
                success: function(data) {
                    var obj = JSON.parse(data);
                    var del = (obj.delete == 'yes');
                    
                    if (typeof $('#event_room') !== 'undefined') $('#event_room').val(obj.resourceId);
                    if (typeof event_start !== 'undefined') event_start.val(obj.date_start);
                    if (typeof $('#event_hour_from') !== 'undefined') $('#event_hour_from').val(obj.hour_start);
                    if (typeof $('#event_minute_from') !== 'undefined') $('#event_minute_from').val(obj.min_start);
                    if (typeof event_end !== 'undefined') event_end.val(obj.date_end);
                    if (typeof $('#event_hour_to') !== 'undefined') $('#event_hour_to').val(obj.hour_end);
                    if (typeof $('#event_minute_to') !== 'undefined') $('#event_minute_to').val(obj.min_end);
                    if (typeof event_name !== 'undefined') event_name.val(obj.name);
                    if (typeof event_phone !== 'undefined') event_phone.val(obj.phone);
                    if (typeof event_message !== 'undefined') event_message.val(obj.message);
                    if (typeof event_remark !== 'undefined') event_remark.val(obj.remark);
                    if (typeof event_summa !== 'undefined') event_summa.val(obj.summa);
                    if (typeof $('#event_dealer') !== 'undefined') $('#event_dealer').val(obj.dealer);
                    if (typeof $('#event_housemaid') !== 'undefined') $('#event_housemaid').val(obj.housemaid);
                    if (typeof $('#event_callcenter') !== 'undefined') $('#event_callcenter').val(obj.callcenter);
                    if (typeof $('#event_range') !== 'undefined') $('#event_range').val(obj.range);
                    if (typeof $('#history') !== 'undefined') $('#history').html(obj.history);
                    
                    if (del)
                        formOpen('delete');
                    else
                        formOpen('nodelete');
                }
            });
        }
    });
    
    global_calendar.fullCalendar('option', 'contentHeight', 250);
    
    console.log('Calendar initialized successfully!');
});
</script>