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
        scrollTime: '08:00',
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
            BX.ajax({
                url: '/local/components/slavko/scheduler/ajax.php?action=formatDate',
                method: 'POST',
                data: {'date': date.toString()},
                dataType: 'json',
                onsuccess: function(response) {
                    console.log(response);
                    showDatetimePopup(response);
                },
                onfailure: function(xhr, status, error) {
                    console.log(error);
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

    global_calendar.fullCalendar('option', 'contentHeight', 250);
    $('.fc-timelineDay-button').on('click',function(){
        global_calendar.fullCalendar('option', 'contentHeight', 250);
    });
    $('.fc-timelineThreeDays-button').on('click',function(){
        global_calendar.fullCalendar('option', 'contentHeight', 250);
    });
    $('.fc-agendaWeek-button').on('click',function(){
        global_calendar.fullCalendar('option', 'contentHeight', 700);
    });
    $('.fc-month-button').on('click',function(){
        global_calendar.fullCalendar('option', 'contentHeight', 500);
    });
    setInterval(function(){global_calendar.fullCalendar('refetchEvents')},10*60000);
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
        '</div>';

    showNativePopup(popupContent);
}

function showDatetimePopup(datetime) {
    var popupContent =
        '<div class="event-popup">' +
            '<div class="event-popup-body">' +
                '<div class="event-row">' +
                '<span class="event-label">Дата:</span>' +
                '<span class="event-value">' + datetime.date + '</span>' +
                '</div>' +
                '<div class="event-row">' +
                '<span class="event-label">Время:</span>' +
                '<span class="event-value">' + datetime.hour + ':' + datetime.minute + '</span>' +
                '</div>' +
                '<div class="event-row">' +
                '<span class="event-label">День недели:</span>' +
                '<span class="event-value">' + getWeekdayName(datetime.weekday) + '</span>' +
                '</div>' +
            '</div>' +
        '</div>';

    showNativePopup(popupContent);
}

// Helper: get weekday name by number (0=Sunday, 1=Monday, etc.)
function getWeekdayName(num) {
    var days = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
    return days[num] || '';
}

function showNativePopup(contentHtml) {
    // 1. Create Overlay
    const overlay = document.createElement('div');
    overlay.className = 'native-modal-overlay';

    // 2. Create Content Container
    const contentDiv = document.createElement('div');
    contentDiv.className = 'native-modal-content';
    contentDiv.innerHTML = contentHtml;

    // 3. Create Close Button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'native-modal-close';
    closeBtn.innerHTML = '&times;'; // The 'X' symbol
    closeBtn.setAttribute('aria-label', 'Close');

    // 4. Assemble
    contentDiv.appendChild(closeBtn);
    overlay.appendChild(contentDiv);
    document.body.appendChild(overlay);

    // 5. Function to Close
    const closePopup = function() {
        overlay.classList.remove('is-visible');

        // Wait for animation to finish before removing from DOM
        setTimeout(() => {
            if (document.body.contains(overlay)) {
                document.body.removeChild(overlay);
            }
        }, 300);
    };

    // 6. Event Listeners

    // Click on Close Button
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        closePopup();
    });

    // Click on Overlay (Background) to close
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closePopup();
        }
    });

    // Press ESC to close
    const escHandler = function(e) {
        if (e.key === 'Escape') {
            closePopup();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);

    // 7. Show (Small timeout to allow CSS transition to catch the class change)
    setTimeout(() => {
        overlay.classList.add('is-visible');
    }, 10);
}
</script>