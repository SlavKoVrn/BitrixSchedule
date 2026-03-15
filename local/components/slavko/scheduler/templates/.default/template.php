<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$asset = \Bitrix\Main\Page\Asset::getInstance();
$componentPath = $this->__component->GetPath();
$templatePath = $componentPath . '/templates/.default';

// CSS
$asset->addCss($templatePath . '/css/fullcalendar.min.css');
$asset->addCss($templatePath . '/css/scheduler.min.css');
$asset->addCss($templatePath . '/css/style.css');

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
        resourceLabelText: 'врач <?= $arResult["WORKER_NAME"] ?>',
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
            BX.ajax({
                url: '/local/components/slavko/scheduler/ajax.php?action=getEvent&worker_id=<?= $arParams['WORKER_ID'] ?>',
                method: 'POST',
                data: {eventId: calEvent.id},
                dataType: 'json',
                onsuccess: function(response) {
                    if (response.error) {
                        console.log(response.error);
                        return;
                    }
                    showEventPopup(response);
                },
                onfailure: function(xhr, status, error) {
                    console.log(error);
                }
            });
        }
    });
    
    global_calendar.fullCalendar('option', 'contentHeight', 300);
});
function showEventPopup(event) {
    // Determine badge style by type
    var badgeClass = '';
    var badgeText = '';

    switch(event.type) {
        case 'weekend':
            badgeClass = 'badge-weekend';
            badgeText = 'Выходной';
            break;
        case 'special':
            badgeClass = 'badge-special';
            badgeText = 'Особый график';
            break;
        default:
            badgeClass = 'badge-working';
            badgeText = 'Рабочий день';
    }

    // Format date for display
    var eventDate = new Date(event.date + 'T00:00:00');
    var formattedDate = eventDate.toLocaleDateString('ru-RU', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Build popup content
    var popupContent =
        '<div class="event-popup">' +
        '<div class="event-popup-header">' +
        '<span class="event-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '<h3 class="event-title">' + event.room_name + '</h3>' +
        '</div>' +
        '<div class="event-popup-body">' +
        '<div class="event-row">' +
        '<span class="event-label">Врач:</span>' +
        '<span class="event-value">' + event.worker_name + '</span>' +
        '</div>' +
        '<div class="event-row">' +
        '<span class="event-label">Дата:</span>' +
        '<span class="event-value">' + formattedDate + '</span>' +
        '</div>' +
        '<div class="event-row">' +
        '<span class="event-label">Время:</span>' +
        '<span class="event-value">' + event.start + ' – ' + event.end + '</span>' +
        '</div>' +
        '<div class="event-row">' +
        '<span class="event-label">День недели:</span>' +
        '<span class="event-value">' + getWeekdayName(event.weekday) + '</span>' +
        '</div>' +
        (event.weekday_note ?
                '<div class="event-row">' +
                '<span class="event-label">Примечание:</span>' +
                '<span class="event-value">' + event.weekday_note + '</span>' +
                '</div>' : ''
        ) +
        '</div>' +
        '<div class="event-popup-footer">' +
            '<button id="eventPopupCloseBtn" class="btn-close">Закрыть</button>' +
        '</div>' +
        '</div>';

    var popup = new BX.PopupWindow("eventPopup_" + event.id, null, {
        content: popupContent,
        zIndex: 2000,
        offsetLeft: 0,
        offsetTop: 0,
        draggable: {restrict: false},
        closeByEsc: true,
        overlay: {backgroundColor: 'black', opacity: '50'},
        buttons: [],
        className: "scheduler-event-popup",
        autoHide: false,
        events: {
            onPopupClose: function() {
                this.destroy();
            }
        }
    });
    popup.show();

    setTimeout(function() {
        var popupNode = popup.popupContainer;
        if (popupNode) {
            popupNode.style.top = '50%';
            popupNode.style.left = '50%';
            popupNode.style.transform = 'translate(-50%, -50%)';
            popupNode.style.position = 'fixed';
        }

        var closeBtn = document.getElementById('eventPopupCloseBtn');
        if (closeBtn) {
            BX.bind(closeBtn, 'click', function(e) {
                e.preventDefault();
                if (popup) {
                    popup.destroy();
                    popup = null;
                }
            });
        }

    }, 10);
}

// Helper: get weekday name by number (0=Sunday, 1=Monday, etc.)
function getWeekdayName(num) {
    var days = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
    return days[num] || '';
}
</script>