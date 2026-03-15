<?php

define('DIR', dirname(__FILE__));

if (class_exists('\Bitrix\Main\Application')) {
    \Bitrix\Main\Application::getInstance()->getManagedCache()->cleanAll();
    \Bitrix\Main\Data\Cache::createInstance()->cleanDir();
    echo "Очищен управляемый и файловый кэш через API Битрикса\n";
}

$paths = [
    DIR . '/bitrix/cache',
    DIR . '/bitrix/managed_cache',
    DIR . '/bitrix/stack_cache',
    DIR . '/bitrix/html_pages',
    DIR . '/bitrix/compiled_templates',
];

foreach ($paths as $path) {
    if (is_dir($path)) {
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
                @$todo($fileinfo->getRealPath());
            }
            echo "Принудительно очищена директория: $path\n";
        } catch (Exception $e) {
            echo "Ошибка при очистке $path: " . $e->getMessage() . "\n";
        }
    }
}

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "OPcache успешно сброшен\n";
    } else {
        echo "Не удалось сбросить OPcache\n";
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
    echo "Текущая сессия уничтожена\n";
}

echo "\nСкрипт очистки завершен.\n";

?>
