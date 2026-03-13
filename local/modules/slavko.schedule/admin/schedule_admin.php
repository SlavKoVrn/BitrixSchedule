<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Application;

$APPLICATION->SetTitle("Настройки расписания сотрудников");
$errorMessage = '';
$successMessage = '';

// Initialize default IDs
$workerIblockId = (int)COption::GetOptionString("slavko.schedule", "worker_iblock_id", 0);
$roomsIblockId = (int)COption::GetOptionString("slavko.schedule", "rooms_iblock_id", 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save']) && check_bitrix_sessid()) {
    if (!CModule::IncludeModule("iblock")) {
        $errorMessage = "Модуль «Информационные блоки» не подключен.";
    } else {
        $selectedWorkerId = (int)($_POST['worker_iblock_id'] ?? 0);
        $selectedRoomsId = (int)($_POST['rooms_iblock_id'] ?? 0);

        // Validate Worker IBlock
        if ($selectedWorkerId > 0) {
            $dbRes = CIBlock::GetList([], ["ID" => $selectedWorkerId], false);
            if (!$dbRes->Fetch()) {
                $errorMessage = "Инфоблок сотрудников (ID=$selectedWorkerId) не найден.";
            }
        }

        // Validate Rooms IBlock
        if (empty($errorMessage) && $selectedRoomsId > 0) {
            $dbRes = CIBlock::GetList([], ["ID" => $selectedRoomsId], false);
            if (!$dbRes->Fetch()) {
                $errorMessage = "Инфоблок кабинетов (ID=$selectedRoomsId) не найден.";
            }
        }

        if (empty($errorMessage)) {
            // Save Options
            COption::SetOptionString("slavko.schedule", "worker_iblock_id", $selectedWorkerId);
            COption::SetOptionString("slavko.schedule", "rooms_iblock_id", $selectedRoomsId);
            $workerIblockId = $selectedWorkerId;
            $roomsIblockId = $selectedRoomsId;
            $successMessage = "Настройки успешно сохранены.";
        }
    }
}

// Fetch IBLOCKS for selects
$iblocks = [];
if (CModule::IncludeModule("iblock")) {
    $dbRes = CIBlock::GetList(["NAME" => "ASC"], [], true);
    while ($row = $dbRes->Fetch()) {
        $iblocks[] = $row;
    }
}

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>

<?php if ($errorMessage): ?>
    <?php CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => $errorMessage]); ?>
<?php endif; ?>

<?php if ($successMessage): ?>
    <?php CAdminMessage::ShowNote($successMessage); ?>
<?php endif; ?>

<form method="post" action="<?= POST_FORM_ACTION_URI ?>" id="config-form">
    <?= bitrix_sessid_post() ?>
    <table class="adm-workarea">
        <tr>
            <td class="adm-detail-content-cell-l" width="40%">
                <strong>Врачи:</strong>
            </td>
            <td class="adm-detail-content-cell-r">
                <select name="worker_iblock_id" style="width: 300px;">
                    <option value="0">-- Не выбран --</option>
                    <?php foreach ($iblocks as $iblock): ?>
                        <option value="<?= (int)$iblock['ID'] ?>" <?= ($workerIblockId == $iblock['ID']) ? 'selected' : '' ?>>
                            <?= (int)$iblock['ID'] ?>. <?= htmlspecialcharsbx($iblock['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l">
                <strong>Адреса:</strong>
            </td>
            <td class="adm-detail-content-cell-r">
                <select name="rooms_iblock_id" style="width: 300px;">
                    <option value="0">-- Не выбран --</option>
                    <?php foreach ($iblocks as $iblock): ?>
                        <option value="<?= (int)$iblock['ID'] ?>" <?= ($roomsIblockId == $iblock['ID']) ? 'selected' : '' ?>>
                            <?= (int)$iblock['ID'] ?>. <?= htmlspecialcharsbx($iblock['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l"></td>
            <td class="adm-detail-content-cell-r">
                <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
            </td>
        </tr>
    </table>
</form>

<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>