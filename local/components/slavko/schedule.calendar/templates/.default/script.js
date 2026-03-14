(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('schedule-calendar');
        if (!container) return;
        
        const calendarEl = document.getElementById('calendar');
        const modal = document.getElementById('event-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalDetails = document.getElementById('modal-details');
        const modalClose = document.querySelector('.schedule-modal-close');
        
        const config = {
            workerId: container.dataset.workerId,
            year: parseInt(container.dataset.year),
            initialView: container.dataset.initView,
            firstHour: parseInt(container.dataset.firstHour),
            lastHour: parseInt(container.dataset.lastHour),
        };
        
        // Initialize FullCalendar
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: config.initialView,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            locale: 'ru',
            firstDay: 1,
            slotMinTime: config.firstHour + ':00:00',
            slotMaxTime: config.lastHour + ':00:00',
            allDaySlot: true,
            nowIndicator: true,
            selectable: false,
            events: window.SCHEDULE_CALENDAR_DATA || [],
            
            // Event styling
            eventDidMount: function(info) {
                const type = info.event.extendedProps.type;
                if (type === 'weekend') {
                    info.el.style.backgroundColor = '#dc3545';
                    info.el.style.borderColor = '#bd2130';
                    info.el.style.color = '#fff';
                } else if (type === 'special') {
                    info.el.style.backgroundColor = '#ffc107';
                    info.el.style.borderColor = '#d39e00';
                    info.el.style.color = '#000';
                } else {
                    info.el.style.backgroundColor = '#28a745';
                    info.el.style.borderColor = '#218838';
                    info.el.style.color = '#fff';
                }
            },
            
            // Click handler
            eventClick: function(info) {
                const props = info.event.extendedProps;
                const start = info.event.start;
                const end = info.event.end;
                
                modalTitle.textContent = info.event.title;
                
                let details = '<p><strong>Тип:</strong> ' + getTypeLabel(props.type) + '</p>';
                details += '<p><strong>Дата:</strong> ' + formatDate(start) + '</p>';
                
                if (props.weekday !== undefined) {
                    details += '<p><strong>День недели:</strong> ' + getWeekdayName(props.weekday) + '</p>';
                }
                
                if (props.roomName) {
                    details += '<p><strong>Кабинет:</strong> ' + props.roomName + '</p>';
                }
                
                if (start && end && info.event.allDay === false) {
                    details += '<p><strong>Время:</strong> ' + 
                        formatTime(start) + ' – ' + formatTime(end) + '</p>';
                }
                
                modalDetails.innerHTML = details;
                modal.style.display = 'block';
            },
            
            // Date click (optional)
            dateClick: function(info) {
                // Could add logic to show "no schedule" message
            },
            
            // Custom rendering for better weekend display
            dayCellDidMount: function(info) {
                // Highlight weekends visually
                if (info.date.getDay() === 0 || info.date.getDay() === 6) {
                    info.el.style.backgroundColor = '#f8f9fa';
                }
            }
        });
        
        calendar.render();
        
        // Modal close handlers
        modalClose.onclick = function() {
            modal.style.display = 'none';
        };
        
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
        
        // Helper functions
        function getTypeLabel(type) {
            const labels = {
                'working': 'Рабочий день',
                'special': 'Особый день',
                'weekend': 'Выходной'
            };
            return labels[type] || type;
        }
        
        function formatDate(date) {
            if (!date) return '';
            return date.toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'long'
            });
        }
        
        function formatTime(date) {
            if (!date) return '';
            return date.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function getWeekdayName(num) {
            const days = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
            return days[num] || '';
        }
    });
})();
