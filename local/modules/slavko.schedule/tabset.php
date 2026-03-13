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

        // Get rooms iblock ID from options
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
                    <li><strong>Рабочий день:</strong> Повторяющийся день недели. Каждый день недели можно использовать только один раз.</li>
                    <li><strong>Особый день:</strong> Конкретная дата, которая переопределяет стандартное расписание.</li>
                    <li><strong>Адрес:</strong> Выберите кабинет из списка (инфоблок кабинетов).</li>
                    <li>Данные будут сохранены в таблицу <strong>sk_schedule</strong> автоматически при сохранении карточки сотрудника.</li>
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
            let usedWorkDays = new Set();
            let usedSpecialDates = new Set();
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
                            data: {
                                q: query
                            }
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
                
                let weekdayOptions = '';
                CONFIG.weekdays.forEach(function(wd) {
                    const selected = (data && data.type === 'working' && data.weekday === wd.value) ? 'selected' : '';
                    const disabled = (usedWorkDays.has(wd.value) && (!data || data.weekday !== wd.value)) ? 'disabled' : '';
                    weekdayOptions += '<option value="' + wd.value + '" ' + selected + ' ' + disabled + '>' + wd.label + '</option>';
                });
                
                // FIX: Show weekday select by default for new rows or working days
                const weekdayDisplay = (!data || data.type === 'working') ? 'block' : 'none';
                const dateDisplay = (data && data.type === 'special') ? 'block' : 'none';
                
                const weekdayValue = (data && data.type === 'working') ? data.weekday : '';
                const dateValue = (data && data.type === 'special') ? data.date : '';
                const startTime = (data && data.start) ? data.start : CONFIG.defaultStart;
                const endTime = (data && data.end) ? data.end : CONFIG.defaultEnd;
                const workingSelected = (!data || data.type === 'working') ? 'selected' : '';
                const specialSelected = (data && data.type === 'special') ? 'selected' : '';
                const roomName = (data && data.roomName) ? data.roomName : '';
                const roomId = (data && data.roomId) ? data.roomId : '';
                
                tr.innerHTML = 
                    '<td class="adm-list-table-cell">' +
                        '<select class="adm-input adm-input-select type-select" data-row="' + rowId + '" style="width:100%;">' +
                            '<option value="working" ' + workingSelected + '>Рабочий день</option>' +
                            '<option value="special" ' + specialSelected + '>Особый день</option>' +
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
                    '<td class="adm-list-table-cell">' +
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
                    '<td class="adm-list-table-cell">' +
                        '<input type="time" class="adm-input adm-input-text start-time" value="' + startTime + '" style="width:70px;">' +
                        '<span style="margin:0 4px;">–</span>' +
                        '<input type="time" class="adm-input adm-input-text end-time" value="' + endTime + '" style="width:70px;">' +
                    '</td>' +
                    '<td class="adm-list-table-cell">' +
                        '<button type="button" class="adm-btn adm-btn-danger adm-btn-sm" data-action="remove" data-row="' + rowId + '">✕</button>' +
                    '</td>';
                
                tbody.appendChild(tr);
                
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
                const roomInput = tr.querySelector('.room-autocomplete');
                const startTime = tr.querySelector('.start-time');
                const endTime = tr.querySelector('.end-time');
                const removeBtn = tr.querySelector('[data-action="remove"]');

                if (typeSelect) {
                    typeSelect.onchange = function() {
                        weekdaySelect.style.display = typeSelect.value === 'working' ? 'block' : 'none';
                        dateInput.style.display = typeSelect.value === 'special' ? 'block' : 'none';
                        updateSelectOptions();
                        autoSaveToJson();
                    };
                }
                
                if (weekdaySelect) {
                    weekdaySelect.onchange = function(e) {
                        const newVal = e.target.value ? parseInt(e.target.value) : null;
                        const oldVal = (initialData && initialData.type === 'working') ? initialData.weekday : null;
                        if (newVal !== null && newVal !== oldVal && usedWorkDays.has(newVal)) {
                            const label = CONFIG.weekdays.find(function(w) { return w.value === newVal; });
                            alert('⚠️ "' + (label ? label.label : '') + '" уже используется в другой строке.');
                            e.target.value = '';
                            return;
                        }
                        if (oldVal !== null && newVal !== oldVal) usedWorkDays.delete(oldVal);
                        if (newVal !== null) usedWorkDays.add(newVal);
                        updateSelectOptions();
                        autoSaveToJson();
                    };
                }
                
                if (dateInput) {
                    dateInput.onchange = function(e) {
                        const newVal = e.target.value;
                        const oldVal = (initialData && initialData.type === 'special') ? initialData.date : null;
                        if (newVal && newVal !== oldVal && usedSpecialDates.has(newVal)) {
                            alert('⚠️ Дата ' + newVal + ' уже используется в другой строке.');
                            e.target.value = '';
                            return;
                        }
                        if (oldVal && newVal !== oldVal) usedSpecialDates.delete(oldVal);
                        if (newVal) usedSpecialDates.add(newVal);
                        autoSaveToJson();
                    };
                }
                
                if (roomInput) {
                    roomInput.onchange = function() {
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
                        const type = typeSelect ? typeSelect.value : '';
                        if (type === 'working' && initialData && initialData.weekday !== undefined) {
                            usedWorkDays.delete(initialData.weekday);
                        }
                        if (type === 'special' && initialData && initialData.date) {
                            usedSpecialDates.delete(initialData.date);
                        }
                        tr.remove();
                        updateSelectOptions();
                        autoSaveToJson();
                    };
                }
            }

            function updateSelectOptions() {
                document.querySelectorAll('.weekday-select').forEach(function(select) {
                    Array.prototype.forEach.call(select.options, function(opt) {
                        if (!opt.value) return;
                        const val = parseInt(opt.value);
                        const currentVal = select.value ? parseInt(select.value) : null;
                        opt.disabled = usedWorkDays.has(val) && currentVal !== val;
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
                } else {
                    const date = dateInput ? dateInput.value : '';
                    return date ? { type: type, date: date, start: start, end: end, roomId: roomId, roomName: roomName } : null;
                }
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
                    // Save only the array of rows, no metadata
                    const output = document.getElementById('scheduleJsonOutput');
                    if (output) {
                        output.value = JSON.stringify(rows);
                    }
                    showStatus('✓ ' + rows.length + ' правил подготовлено', true);
                }, 300);
            }

            function loadFromProperty() {
                const output = document.getElementById('scheduleJsonOutput');
                const existingData = output ? output.value : '';
                if (!existingData) {
                    addRow();
                    return;
                }
                try {
                    // Parse as array directly
                    let rows = JSON.parse(existingData);
                    if (!Array.isArray(rows)) {
                        // Backward compatibility: check for old format
                        if (rows && rows.rows && Array.isArray(rows.rows)) {
                            rows = rows.rows;
                        } else {
                            addRow();
                            return;
                        }
                    }
                    usedWorkDays.clear();
                    usedSpecialDates.clear();
                    const tbody = document.getElementById('scheduleBody');
                    if (tbody) {
                        tbody.innerHTML = '';
                    }
                    rowCounter = 0;
                    rows.forEach(function(row) {
                        if (row.type === 'working' && row.weekday !== undefined) {
                            usedWorkDays.add(row.weekday);
                        }
                        if (row.type === 'special' && row.date) {
                            usedSpecialDates.add(row.date);
                        }
                        addRow(row);
                    });
                    showStatus('✓ Загружено ' + rows.length + ' правил', true);
                } catch (e) {
                    console.error('Failed to load schedule:', e);
                    addRow();
                }
            }

            function clearAll() {
                if (!confirm('Очистить всё расписание для этого сотрудника?')) return;
                usedWorkDays.clear();
                usedSpecialDates.clear();
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
