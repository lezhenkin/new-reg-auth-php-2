<?php
// Создаем константу, в которой будет храниться путь к директории сайта на сервере
define('SITE_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR);

/**
 * Создаем константу, которая предотвратит прямой доступ к файлам наших классов через веб-сервер
 * Выражением defined('MYSITE') || exit('Прямой доступ к файлу запрещен') мы этот доступ и ограничим
 */
define('MYSITE', TRUE);

// Для запрета выполнения ini_set в сценариях нужно установить в TRUE
define('DENY_INI_SET', FALSE);

if (!defined('DENY_INI_SET') || !DENY_INI_SET)
{
	ini_set('display_errors', 1);
}

// Подключаем класс ядра
require_once(SITE_FOLDER . "classes" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "core.php");

// Инициализируем ядро системы
Core::init();
?>