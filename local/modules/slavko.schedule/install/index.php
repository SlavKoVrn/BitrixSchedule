<?php
// /local/modules/slavko.schedule/install/index.php
Class slavko_schedule extends CModule
{
    var $MODULE_ID = "slavko.schedule";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_GROUP_RIGHTS = "Y";

    function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        // Hardcoded strings
        $this->MODULE_NAME = 'Расписание сотрудников';
        $this->MODULE_DESCRIPTION = 'Настройка расписания сотрудников';
    }

    function InstallEvents()
    {
        return true;
    }

    function UnInstallEvents()
    {
        return true;
    }

    function InstallFiles()
    {
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . '/local/modules/' . $this->MODULE_ID . '/components',
            $_SERVER["DOCUMENT_ROOT"] . '/local/components/slavko',
            true,
            true
        );
        if (file_exists($_SERVER["DOCUMENT_ROOT"] . '/local/modules/' . $this->MODULE_ID . '/admin')) {
            CopyDirFiles(
                $_SERVER["DOCUMENT_ROOT"] . '/local/modules/' . $this->MODULE_ID . '/admin',
                $_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin',
                true,
                true
            );
        }
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFilesEx('/local/components/slavko/schedule');
        DeleteDirFilesEx('/bitrix/admin/schedule_admin.php');
        return true;
    }

    function DoInstall()
    {
        global $APPLICATION;
        $this->InstallFiles();
        $this->InstallDB();
        RegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            'Модуль "' . $this->MODULE_NAME . '" установлен',
            __DIR__ . '/messages/success.php'
        );
    }

    function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
        $this->UnInstallDB();
        $APPLICATION->IncludeAdminFile(
            'Модуль "' . $this->MODULE_NAME . '" удален',
            __DIR__ . '/messages/uninstall.php'
        );
    }

    public function InstallDB()
    {
        $connection = \Bitrix\Main\Application::getConnection();
        if (!$connection->isTableExists('sk_schedule')) {
            $sqlFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/db/mysql/install.sql';
            if (!file_exists($sqlFile)) {
                AddMessage2Log('SQL file not found: ' . $sqlFile, $this->MODULE_ID);
                return false;
            }
            $sqlContent = file_get_contents($sqlFile);
            if ($sqlContent === false) {
                AddMessage2Log('Cannot read SQL file.', $this->MODULE_ID);
                return false;
            }
            try {
                $connection->startTransaction();
                $result = $connection->executeSqlBatch($sqlContent);
                if ($result !== null) {
                    foreach ((array)$result as $error) {
                        AddMessage2Log('SQL Error: ' . print_r($error, true), $this->MODULE_ID);
                    }
                    $connection->rollbackTransaction();
                    return false;
                }
                $connection->commitTransaction();
            } catch (\Exception $e) {
                $connection->rollbackTransaction();
                AddMessage2Log('DB Error: ' . $e->getMessage(), $this->MODULE_ID);
                return false;
            }
        }
        return true;
    }

    public function UnInstallDB()
    {
        $connection = \Bitrix\Main\Application::getConnection();
        try {
            $connection->dropTable('sk_schedule');
        } catch (\Exception $e) {
            AddMessage2Log('Drop table failed: ' . $e->getMessage(), $this->MODULE_ID);
        }
        return true;
    }
}