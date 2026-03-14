<?php
use Bitrix\Main\Context;
use Bitrix\Main\Application;
class ScheduleTabset
{
    public function getTabList($elementInfo)
    {
        $request = Context::getCurrent()->getRequest();
        $workersIblockId = (int)COption::GetOptionString("slavko.schedule", "worker_iblock_id", 0);
        $addTabs = $elementInfo['ID'] > 0
            && $elementInfo['IBLOCK']['ID'] == $workersIblockId
            && (!isset($request['action']) || $request['action'] != 'copy');
        return $addTabs ? [
            [
                'DIV'   => 'schedule_tab',
                'SORT'  => 500,
                'TAB'   => 'Расписание',
                'TITLE' => 'Настройка расписания сотрудника',
            ],
        ] : null;
    }

    public function showTabContent($div, $elementInfo, $formData)
    {
        $workerId = (int)$elementInfo['ID'];
        $iblockId = (int)$elementInfo['IBLOCK']['ID'];
        $existingSchedule = self::loadSchedule($workerId);
        $roomsIblockId = (int)COption::GetOptionString("slavko.schedule", "rooms_iblock_id", 0);
        self::renderWidget($workerId, $iblockId, $existingSchedule, $roomsIblockId);
    }

    private static function loadSchedule($workerId)
    {
        global $DB;
        $sql = "SELECT SCHEDULE FROM sk_schedule WHERE WORKER_ID = " . $DB->ForSql($workerId);
        $res = $DB->Query($sql, false);
        if ($row = $res->Fetch()) {
            return $row['SCHEDULE'];
        }
        return '';
    }

    private static function renderWidget($workerId, $iblockId, $existingSchedule, $roomsIblockId)
    {
        $currentYear = date('Y');
        $existingScheduleEscaped = htmlspecialcharsbx($existingSchedule);
        ?>
        <div class="schedule-widget-container" style="padding:10px;">
            <div class="toolbar" style="margin-bottom:15px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="adm-btn adm-btn-save" id="addRow">+ Добавить строку</button>
                <button type="button" class="adm-btn adm-btn-danger" id="clearAll">🗑️ Очистить всё</button>
                <span id="status" style="margin-left:auto;font-size:13px;color:#666;"></span>
            </div>
            <table class="adm-list-table" style="width:100%;">
                <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell"><strong>Тип</strong></td>
                    <td class="adm-list-table-cell"><strong>День / Дата</strong></td>
                    <td class="adm-list-table-cell"><strong>Адрес</strong></td>
                    <td class="adm-list-table-cell"><strong>Часы</strong></td>
                    <td class="adm-list-table-cell"></td>
                </tr>
                </thead>
                <tbody id="scheduleBody"></tbody>
            </table>
            <div style="margin:10px 0;padding:10px;background:#f5f5f5;border-radius:4px;font-size:13px;">
                <strong>ℹ️ Правила:</strong>
                <ul style="margin:5px 0 0 20px;">
                    <li><strong>Рабочий день:</strong> Повторяющийся день недели. Каждый день недели можно использовать только один раз <em>для каждого кабинета</em>.</li>
                    <li><strong>Особый день:</strong> Конкретная дата, которая переопределяет стандартное расписание.</li>
                    <li><strong>Выходной:</strong> Конкретная дата выходного дня (переопределяет рабочий и особый день). Без адреса и часов.</li>
                    <li>Данные будут сохранены в таблицу <strong>sk_schedule</strong> автоматически при сохранении карточки сотрудника.</li>
                    <li>При сохранении расписание будет развёрнуто на все дни текущего года (<?= $currentYear ?>) по каждому кабинету.</li>
                </ul>
            </div>
            <input type="hidden" name="SLAVKO_SCHEDULE_DATA" id="scheduleJsonOutput" value="<?=$existingScheduleEscaped?>">
        </div>
        <script>
        (function() {
        'use strict';
        if (window.SlavkoScheduleWidgetInitialized) return;
        window.SlavkoScheduleWidgetInitialized = true;
        const CONFIG = {
            year: <?=$currentYear?>,
            defaultStart: "09:00",
            defaultEnd: "18:00",
            weekdays: [
                { label: "Понедельник", value: 1 },
                { label: "Вторник", value: 2 },
                { label: "Среда", value: 3 },
                { label: "Четверг", value: 4 },
                { label: "Пятница", value: 5 },
                { label: "Суббота", value: 6 },
                { label: "Воскресенье", value: 0 }
            ]
        };
        let rowCounter = 0;
        // Track used weekdays/dates per room_id
        let usedWorkDaysByRoom = {};
        let usedSpecialDatesByRoom = {};
        let usedWeekendDatesByRoom = {};
        let autoSaveTimeout = null;

        function init() {
            const addRowBtn = document.getElementById('addRow');
            const clearAllBtn = document.getElementById('clearAll');
            if (addRowBtn) {
                addRowBtn.onclick = function(e) {
                    e.preventDefault();
                    addRow();
                };
            }
            if (clearAllBtn) {
                clearAllBtn.onclick = function(e) {
                    e.preventDefault();
                    clearAll();
                };
            }
            loadFromProperty();
        }

        function debounce(func, delay) {
            let timer;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function() {
                    func.apply(context, args);
                }, delay);
            };
        }

        function initRoomAutocomplete(input, hiddenInput, rowId) {
            const container = input.closest('.autocomplete-container');
            const suggestionsBox = container.querySelector('.suggestions');
            document.addEventListener('click', function(e) {
                if (!container.contains(e.target)) {
                    suggestionsBox.style.display = 'none';
                }
            });
            const fetchSuggestions = debounce(function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    suggestionsBox.style.display = 'none';
                    return;
                }
                if (typeof BX !== 'undefined' && BX.ajax) {
                    BX.ajax.runAction('slavko:schedule.MainController.getRooms', {
                        data: { q: query }
                    }).then(function(response) {
                        suggestionsBox.innerHTML = '';
                        if (!response || !response.data || !Array.isArray(response.data) || response.data.length === 0) {
                            suggestionsBox.style.display = 'none';
                            return;
                        }
                        response.data.forEach(function(item) {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.textContent = '[' + item.id + '] ' + item.name;
                            div.addEventListener('click', function() {
                                input.value = item.name;
                                input.dataset.roomId = item.id;
                                if (hiddenInput) hiddenInput.value = item.id;
                                suggestionsBox.style.display = 'none';
                                onRoomChange(rowId, item.id);
                                autoSaveToJson();
                            });
                            suggestionsBox.appendChild(div);
                        });
                        suggestionsBox.style.display = 'block';
                    }).catch(function(error) {
                        console.error('AJAX error:', error);
                        suggestionsBox.style.display = 'none';
                    });
                }
            }, 300);
            input.addEventListener('input', fetchSuggestions);
            input.addEventListener('focus', function() {
                if (this.value.length >= 2) fetchSuggestions.call(this);
            });
        }

        function getUsedSetsForRoom(roomId) {
            if (!usedWorkDaysByRoom[roomId]) usedWorkDaysByRoom[roomId] = new Set();
            if (!usedSpecialDatesByRoom[roomId]) usedSpecialDatesByRoom[roomId] = new Set();
            if (!usedWeekendDatesByRoom[roomId]) usedWeekendDatesByRoom[roomId] = new Set();
            return {
                workDays: usedWorkDaysByRoom[roomId],
                specialDates: usedSpecialDatesByRoom[roomId],
                weekendDates: usedWeekendDatesByRoom[roomId]
            };
        }

        function onRoomChange(rowId, newRoomId) {
            const tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
            if (!tr) return;
            const typeSelect = tr.querySelector('.type-select');
            const weekdaySelect = tr.querySelector('.weekday-select');
            if (!typeSelect || typeSelect.value !== 'working' || !weekdaySelect.value) return;
            
            const weekday = parseInt(weekdaySelect.value);
            const oldRoomId = tr.dataset.lastRoomId || '0';
            
            if (oldRoomId && oldRoomId !== newRoomId) {
                const oldSets = getUsedSetsForRoom(oldRoomId);
                oldSets.workDays.delete(weekday);
            }
            
            tr.dataset.lastRoomId = newRoomId;
            updateSelectOptions();
        }

        function addRow(data) {
            data = data || null;
            const rowId = 'row-' + (++rowCounter);
            const tbody = document.getElementById('scheduleBody');
            if (!tbody) {
                console.error('scheduleBody not found');
                return;
            }
            const tr = document.createElement('tr');
            tr.className = 'adm-list-table-row';
            tr.dataset.rowId = rowId;

            const roomId = (data && data.roomId) ? data.roomId : '';
            const sets = getUsedSetsForRoom(roomId || '0');

            let weekdayOptions = '';
            CONFIG.weekdays.forEach(function(wd) {
                const selected = (data && data.type === 'working' && data.weekday === wd.value) ? 'selected' : '';
                const disabled = (sets.workDays.has(wd.value) && (!data || data.weekday !== wd.value)) ? 'disabled' : '';
                weekdayOptions += '<option value="' + wd.value + '" ' + selected + ' ' + disabled + '>' + wd.label + '</option>';
            });

            const type = data ? data.type : 'working';
            const weekdayDisplay = (type === 'working') ? 'block' : 'none';
            const dateDisplay = (type === 'special' || type === 'weekend') ? 'block' : 'none';
            const addressDisplay = (type === 'working' || type === 'special') ? 'table-cell' : 'none';
            const hoursDisplay = (type === 'working' || type === 'special') ? 'table-cell' : 'none';

            const dateValue = (data && (data.type === 'special' || data.type === 'weekend')) ? data.date : '';
            const startTime = (data && data.start) ? data.start : CONFIG.defaultStart;
            const endTime = (data && data.end) ? data.end : CONFIG.defaultEnd;
            const workingSelected = (type === 'working') ? 'selected' : '';
            const specialSelected = (type === 'special') ? 'selected' : '';
            const weekendSelected = (type === 'weekend') ? 'selected' : '';
            const roomName = (data && data.roomName) ? data.roomName : '';

            tr.innerHTML =
                '<td class="adm-list-table-cell">' +
                '<select class="adm-input adm-input-select type-select" data-row="' + rowId + '" style="width:100%;">' +
                '<option value="working" ' + workingSelected + '>Рабочий день</option>' +
                '<option value="special" ' + specialSelected + '>Особый день</option>' +
                '<option value="weekend" ' + weekendSelected + '>Выходной</option>' +
                '</select>' +
                '</td>' +
                '<td class="adm-list-table-cell">' +
                '<select class="adm-input adm-input-select weekday-select" style="width:100%;display:' + weekdayDisplay + ';">' +
                '<option value="">-- Выберите день недели --</option>' +
                weekdayOptions +
                '</select>' +
                '<input type="date" class="adm-input adm-input-text date-input" ' +
                'style="width:100%;display:' + dateDisplay + ';" ' +
                'value="' + dateValue + '" ' +
                'min="' + CONFIG.year + '-01-01" max="' + CONFIG.year + '-12-31">' +
                '</td>' +
                '<td class="adm-list-table-cell" style="display:' + addressDisplay + ';">' +
                '<div class="autocomplete-container" style="position:relative;width:100%;">' +
                '<input type="text" class="adm-input adm-input-text room-autocomplete" ' +
                'style="width:100%;box-sizing:border-box;" ' +
                'value="' + roomName + '" ' +
                'data-row="' + rowId + '" ' +
                'data-room-id="' + roomId + '" ' +
                'placeholder="Начните вводить название..." ' +
                'autocomplete="off">' +
                '<input type="hidden" class="room-id-hidden" name="room_id_' + rowId + '" value="' + roomId + '">' +
                '<div class="suggestions" style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ccc;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 2px 6px rgba(0,0,0,0.1);display:none;"></div>' +
                '</div>' +
                '</td>' +
                '<td class="adm-list-table-cell" style="display:' + hoursDisplay + ';">' +
                '<input type="time" class="adm-input adm-input-text start-time" value="' + startTime + '" style="width:70px;">' +
                '<span style="margin:0 4px;">–</span>' +
                '<input type="time" class="adm-input adm-input-text end-time" value="' + endTime + '" style="width:70px;">' +
                '</td>' +
                '<td class="adm-list-table-cell">' +
                '<button type="button" class="adm-btn adm-btn-danger adm-btn-sm" data-action="remove" data-row="' + rowId + '">✕</button>' +
                '</td>';

            tbody.appendChild(tr);
            tr.dataset.lastRoomId = roomId || '0';

            const roomInput = tr.querySelector('.room-autocomplete');
            const roomHiddenInput = tr.querySelector('.room-id-hidden');
            if (roomInput) {
                initRoomAutocomplete(roomInput, roomHiddenInput, rowId);
            }
            setupRowListeners(tr, rowId, data);
            autoSaveToJson();
        }

        function setupRowListeners(tr, rowId, initialData) {
            const typeSelect = tr.querySelector('.type-select');
            const weekdaySelect = tr.querySelector('.weekday-select');
            const dateInput = tr.querySelector('.date-input');
            const roomCell = tr.querySelectorAll('.adm-list-table-cell')[2];
            const hoursCell = tr.querySelectorAll('.adm-list-table-cell')[3];
            const roomInput = tr.querySelector('.room-autocomplete');
            const roomHiddenInput = tr.querySelector('.room-id-hidden');
            const startTime = tr.querySelector('.start-time');
            const endTime = tr.querySelector('.end-time');
            const removeBtn = tr.querySelector('[data-action="remove"]');

            if (typeSelect) {
                typeSelect.onchange = function() {
                    const newType = typeSelect.value;
                    const currentRoomId = roomHiddenInput ? roomHiddenInput.value : '0';
                    const sets = getUsedSetsForRoom(currentRoomId);
                    
                    weekdaySelect.style.display = newType === 'working' ? 'block' : 'none';
                    dateInput.style.display = (newType === 'special' || newType === 'weekend') ? 'block' : 'none';
                    roomCell.style.display = (newType === 'working' || newType === 'special') ? 'table-cell' : 'none';
                    hoursCell.style.display = (newType === 'working' || newType === 'special') ? 'table-cell' : 'none';
                    
                    if (initialData && initialData.type === 'working' && initialData.weekday !== undefined) {
                        sets.workDays.delete(initialData.weekday);
                    }
                    if (initialData && initialData.type === 'special' && initialData.date) {
                        sets.specialDates.delete(initialData.date);
                    }
                    if (initialData && initialData.type === 'weekend' && initialData.date) {
                        sets.weekendDates.delete(initialData.date);
                    }
                    
                    updateSelectOptions();
                    autoSaveToJson();
                };
            }

            if (weekdaySelect) {
                weekdaySelect.onchange = function(e) {
                    const currentRoomId = roomHiddenInput ? roomHiddenInput.value : '0';
                    const sets = getUsedSetsForRoom(currentRoomId);
                    const newVal = e.target.value ? parseInt(e.target.value) : null;
                    const oldVal = (initialData && initialData.type === 'working') ? initialData.weekday : null;
                    
                    if (newVal !== null && newVal !== oldVal && sets.workDays.has(newVal)) {
                        const label = CONFIG.weekdays.find(function(w) { return w.value === newVal; });
                        alert('⚠️ "' + (label ? label.label : '') + '" уже используется для этого кабинета.');
                        e.target.value = '';
                        return;
                    }
                    if (oldVal !== null && newVal !== oldVal) sets.workDays.delete(oldVal);
                    if (newVal !== null) sets.workDays.add(newVal);
                    updateSelectOptions();
                    autoSaveToJson();
                };
            }

            if (dateInput) {
                dateInput.onchange = function(e) {
                    const currentRoomId = roomHiddenInput ? roomHiddenInput.value : '0';
                    const sets = getUsedSetsForRoom(currentRoomId);
                    const newVal = e.target.value;
                    const oldVal = (initialData && (initialData.type === 'special' || initialData.type === 'weekend')) ? initialData.date : null;
                    const currentType = typeSelect ? typeSelect.value : '';
                    
                    if (newVal && newVal !== oldVal) {
                        if (currentType === 'weekend' && sets.weekendDates.has(newVal)) {
                            alert('⚠️ Дата ' + newVal + ' уже используется как выходной для этого кабинета.');
                            e.target.value = '';
                            return;
                        }
                        if (currentType === 'special' && sets.specialDates.has(newVal)) {
                            alert('⚠️ Дата ' + newVal + ' уже используется как особый день для этого кабинета.');
                            e.target.value = '';
                            return;
                        }
                    }
                    if (oldVal && newVal !== oldVal) {
                        if (initialData && initialData.type === 'weekend') sets.weekendDates.delete(oldVal);
                        if (initialData && initialData.type === 'special') sets.specialDates.delete(oldVal);
                    }
                    if (newVal) {
                        if (currentType === 'weekend') sets.weekendDates.add(newVal);
                        if (currentType === 'special') sets.specialDates.add(newVal);
                    }
                    autoSaveToJson();
                };
            }

            if (roomInput) {
                roomInput.onchange = function() {
                    const newRoomId = roomHiddenInput ? roomHiddenInput.value : '0';
                    onRoomChange(rowId, newRoomId);
                    autoSaveToJson();
                };
            }
            if (startTime) {
                startTime.onchange = function() {
                    autoSaveToJson();
                };
            }
            if (endTime) {
                endTime.onchange = function() {
                    autoSaveToJson();
                };
            }
            if (removeBtn) {
                removeBtn.onclick = function() {
                    const currentRoomId = roomHiddenInput ? roomHiddenInput.value : '0';
                    const sets = getUsedSetsForRoom(currentRoomId);
                    const type = typeSelect ? typeSelect.value : '';
                    
                    if (type === 'working' && initialData && initialData.weekday !== undefined) {
                        sets.workDays.delete(initialData.weekday);
                    }
                    if (type === 'special' && initialData && initialData.date) {
                        sets.specialDates.delete(initialData.date);
                    }
                    if (type === 'weekend' && initialData && initialData.date) {
                        sets.weekendDates.delete(initialData.date);
                    }
                    tr.remove();
                    updateSelectOptions();
                    autoSaveToJson();
                };
            }
        }

        function updateSelectOptions() {
            document.querySelectorAll('#scheduleBody tr').forEach(function(tr) {
                const roomHiddenInput = tr.querySelector('.room-id-hidden');
                const weekdaySelect = tr.querySelector('.weekday-select');
                const typeSelect = tr.querySelector('.type-select');
                if (!roomHiddenInput || !weekdaySelect || !typeSelect) return;
                
                const roomId = roomHiddenInput.value || '0';
                const sets = getUsedSetsForRoom(roomId);
                const currentVal = weekdaySelect.value ? parseInt(weekdaySelect.value) : null;
                
                Array.prototype.forEach.call(weekdaySelect.options, function(opt) {
                    if (!opt.value) return;
                    const val = parseInt(opt.value);
                    opt.disabled = typeSelect.value === 'working' && sets.workDays.has(val) && currentVal !== val;
                });
            });
        }

        function getRowData(tr) {
            const typeSelect = tr.querySelector('.type-select');
            const weekdaySelect = tr.querySelector('.weekday-select');
            const dateInput = tr.querySelector('.date-input');
            const roomInput = tr.querySelector('.room-autocomplete');
            const roomHiddenInput = tr.querySelector('.room-id-hidden');
            const startTime = tr.querySelector('.start-time');
            const endTime = tr.querySelector('.end-time');

            if (!typeSelect) return null;
            const type = typeSelect.value;
            const start = startTime ? startTime.value : '';
            const end = endTime ? endTime.value : '';
            const roomId = roomHiddenInput ? parseInt(roomHiddenInput.value) : null;
            const roomName = roomInput ? roomInput.value : '';

            if (type === 'working') {
                const weekday = weekdaySelect ? weekdaySelect.value : '';
                return weekday ? { type: type, weekday: parseInt(weekday), start: start, end: end, roomId: roomId, roomName: roomName } : null;
            } else if (type === 'special' || type === 'weekend') {
                const date = dateInput ? dateInput.value : '';
                const data = { type: type, date: date, roomId: roomId, roomName: roomName };
                if (type === 'special') {
                    data.start = start;
                    data.end = end;
                }
                return date ? data : null;
            }
            return null;
        }

        function getAllRowsData() {
            const rows = document.querySelectorAll('#scheduleBody tr');
            const result = [];
            rows.forEach(function(tr) {
                const rowData = getRowData(tr);
                if (rowData !== null) {
                    result.push(rowData);
                }
            });
            return result;
        }

        function autoSaveToJson() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function() {
                const rows = getAllRowsData();
                const output = document.getElementById('scheduleJsonOutput');
                if (output) {
                    output.value = JSON.stringify(rows);
                }
                showStatus('✓ ' + rows.length + ' правил подготовлено', true);
            }, 300);
        }

        function extractRulesFromExpanded(expandedData) {
            const rules = [];
            const processedRooms = {};
            
            for (const roomId in expandedData) {
                if (roomId === 'roomName') continue;
                const roomData = expandedData[roomId];
                if (!roomData || typeof roomData !== 'object') continue;
                
                const roomName = roomData.roomName || 'Кабинет #' + roomId;
                const workingDays = {};
                const specialDays = {};
                const weekendDays = {};
                
                for (const dateStr in roomData) {
                    if (dateStr === 'roomName') continue;
                    const dayData = roomData[dateStr];
                    if (!dayData || typeof dayData !== 'object') continue;
                    
                    if (dayData.source === 'working' || (dayData.type === 'working' && dayData.weekday !== undefined && dayData.start && dayData.end)) {
                        const weekday = dayData.weekday;
                        if (!workingDays[weekday]) {
                            workingDays[weekday] = {
                                type: 'working',
                                weekday: weekday,
                                start: dayData.start,
                                end: dayData.end,
                                roomId: parseInt(roomId),
                                roomName: roomName
                            };
                        }
                    } else if (dayData.source === 'special' || dayData.type === 'special') {
                        if (!specialDays[dateStr]) {
                            specialDays[dateStr] = {
                                type: 'special',
                                date: dateStr,
                                start: dayData.start,
                                end: dayData.end,
                                roomId: parseInt(roomId),
                                roomName: roomName
                            };
                        }
                    } else if (dayData.source === 'weekend' || dayData.type === 'weekend') {
                        if (!weekendDays[dateStr]) {
                            weekendDays[dateStr] = {
                                type: 'weekend',
                                date: dateStr,
                                roomId: parseInt(roomId),
                                roomName: roomName
                            };
                        }
                    }
                }
                
                for (const wd in workingDays) rules.push(workingDays[wd]);
                for (const d in specialDays) rules.push(specialDays[d]);
                for (const d in weekendDays) rules.push(weekendDays[d]);
            }
            
            return rules;
        }

        function loadFromProperty() {
            const output = document.getElementById('scheduleJsonOutput');
            const existingData = output ? output.value : '';
            if (!existingData) {
                addRow();
                return;
            }
            try {
                let parsed = JSON.parse(existingData);
                let rules = [];
                
                if (Array.isArray(parsed)) {
                    rules = parsed;
                } else if (typeof parsed === 'object' && parsed !== null) {
                    rules = extractRulesFromExpanded(parsed);
                }
                
                if (rules.length === 0) {
                    addRow();
                    return;
                }
                
                usedWorkDaysByRoom = {};
                usedSpecialDatesByRoom = {};
                usedWeekendDatesByRoom = {};
                
                const tbody = document.getElementById('scheduleBody');
                if (tbody) {
                    tbody.innerHTML = '';
                }
                rowCounter = 0;
                
                rules.forEach(function(row) {
                    const roomId = row.roomId || '0';
                    const sets = getUsedSetsForRoom(roomId);
                    
                    if (row.type === 'working' && row.weekday !== undefined) {
                        sets.workDays.add(row.weekday);
                    }
                    if (row.type === 'special' && row.date) {
                        sets.specialDates.add(row.date);
                    }
                    if (row.type === 'weekend' && row.date) {
                        sets.weekendDates.add(row.date);
                    }
                    addRow(row);
                });
                
                updateSelectOptions();
                showStatus('✓ Загружено ' + rules.length + ' правил', true);
            } catch (e) {
                console.error('Failed to load schedule:', e);
                addRow();
            }
        }

        function clearAll() {
            if (!confirm('Очистить всё расписание для этого сотрудника?')) return;
            usedWorkDaysByRoom = {};
            usedSpecialDatesByRoom = {};
            usedWeekendDatesByRoom = {};
            const tbody = document.getElementById('scheduleBody');
            if (tbody) {
                tbody.innerHTML = '';
            }
            const output = document.getElementById('scheduleJsonOutput');
            if (output) {
                output.value = '';
            }
            rowCounter = 0;
            addRow();
            showStatus('✓ Очищено', true);
        }

        function showStatus(msg, ok) {
            const el = document.getElementById('status');
            if (el) {
                el.textContent = msg;
                el.style.color = ok ? '#28a745' : '#dc3545';
                setTimeout(function() { el.textContent = ''; }, 4000);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        })();
        </script>
        <style>
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .suggestion-item:hover {
            background-color: #f5f5f5;
        }
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        </style>
        <?php
    }

    public function check()
    {
        return true;
    }

    public function action()
    {
        return true;
    }
}
