<?php

use Vit\Doctor\Helper;

$APPLICATION->IncludeComponent('bitrix:news.detail', 'vd-doctor', [
		'ACTIVE_DATE_FORMAT' => '',
		'ADD_ELEMENT_CHAIN' => 'N',
		'ADD_SECTIONS_CHAIN' => 'N',
		'AJAX_MODE' => 'N',
		'AJAX_OPTION_ADDITIONAL' => '',
		'AJAX_OPTION_HISTORY' => 'N',
		'AJAX_OPTION_JUMP' => 'N',
		'AJAX_OPTION_STYLE' => 'N',
		'BROWSER_TITLE' => '-',
		'CACHE_GROUPS' => 'N',
		'CACHE_TIME' => '36000000',
		'CACHE_TYPE' => 'A',
		'CHECK_DATES' => 'Y',
		'DETAIL_URL' => '',
		'DISPLAY_BOTTOM_PAGER' => 'N',
		'DISPLAY_TOP_PAGER' => 'N',
		'ELEMENT_CODE' => '',
		'ELEMENT_ID' => $GLOBALS['VD_SETTINGS']['doctor'],
		'FIELD_CODE' => ['PREVIEW_PICTURE'],
		'IBLOCK_ID' => Helper::getIblockIdByCode('vd-doctors'),
		'IBLOCK_TYPE' => 'vd_employees',
		'IBLOCK_URL' => '',
		'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
		'MESSAGE_404' => '',
		'META_DESCRIPTION' => '-',
		'META_KEYWORDS' => '-',
		'PAGER_BASE_LINK_ENABLE' => 'N',
		'PAGER_SHOW_ALL' => 'N',
		'PAGER_TEMPLATE' => '',
		'PAGER_TITLE' => '',
		'PROPERTY_CODE' => ['STATISTICS', 'VIDEO', 'SPECIALIZATION', 'EDUCATION', 'EXPERIENCE'],
		'SET_BROWSER_TITLE' => 'N',
		'SET_CANONICAL_URL' => 'N',
		'SET_LAST_MODIFIED' => 'N',
		'SET_META_DESCRIPTION' => 'N',
		'SET_META_KEYWORDS' => 'N',
		'SET_STATUS_404' => 'N',
		'SET_TITLE' => 'N',
		'SHOW_404' => 'N',
		'STRICT_SECTION_CHECK' => 'N',
		'USE_PERMISSIONS' => 'N'
]);

$APPLICATION->IncludeComponent(
    "slavko:schedule.calendar",
    ".default",
    [
        "WORKER_ID" => 53,
        "YEAR" => date('Y'),
        "INIT_VIEW" => "dayGridMonth", // dayGridMonth, timeGridWeek, timeGridDay, listMonth
        "FIRST_HOUR" => "8",
        "LAST_HOUR" => "20",
    ],
    false
);