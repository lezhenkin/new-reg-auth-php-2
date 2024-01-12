<?php
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class Core 
{
    // Статус инициализации ядра системы
    static private $_init = FALSE;

    // Путь к месту хранения файлов с определением пользовательских классов
    static public $classesPath = NULL;

    // Свойство, в котором будет храниться информация об автозагруженных классах
    static private $_autoloadCache = [];

    // Параметры каких-либо драйверов или модулей. Например, параметры подключения к СУБД
    static public $config = NULL;
    
    /**
     * Автозагрузка определений классов
     */
    private static function _autoload($class)
    {
        // Если ранее класс уже был загружен, ничего не делаем
        if (isset(static::$_autoloadCache[$class]))
        {
            return static::$_autoloadCache[$class];
        }
        
        $return = FALSE;
        
        // Получаем путь к файлу на сервере с определением класса
        $sPath = static::$classesPath . static::getClassPath($class);
        
        // Если такой файл на диске есть, подключаем его
        if (is_file($sPath))
        {
            include($sPath);

            $return = TRUE;
        }

        static::$_autoloadCache[$class] = $return;

        return $return;
    }

    /**
     * Возвращает стаус инициализации ядра системы
     * @return boolean TRUE | FALSE
     */
    static public function isInit()
    {
        return static::$_init;
    }

    /**
     * Инициализирует ядро системы
     */
    static public function init()
    {
        // Если ядро уже было инициализировано, ничего не делаем
        if (static::isInit())
        {
            return TRUE;
        }

        // Устанавливаем путь к месту хранения файлов с определениями пользовательских классов на сервере
        static::setClassesPath();

        // Инициализируем наши пользовательские классы
        static::registerCallbackFunction();

        // Вызываем класс для получения параметров конфигурации чего-либо, сохраняем его в ядре
        static::$config = Core_Config::instance();

        // Установим кодировку UTF-8
        mb_internal_encoding('UTF-8');
        
        try {
            // Устанавливаем соединение с СУБД
            Core_Database::instance()->connect();
        }
        // В случае ошибки останавливаем работу сценария
        catch (Exception $e)
        {
            echo "<p>{$e->getMessage()}</p>";

            die();
        }

        // Запускаем сессию
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            session_start();
        }
    }

    /**
     * Устанавливает пути к месту хранения файлов с определениями пользовательских классов на сервере
     */
    static public function setClassesPath()
    {
        static::$classesPath = SITE_FOLDER . "classes" . DIRECTORY_SEPARATOR;
    }

    /**
     * Регистрирует реализации пользовательских классов
     */
    static public function registerCallbackFunction()
    {
        spl_autoload_register(array('Core', '_autoload'));
    }

    /**
     * Получает путь к файлу на сервере с определением класса
     */
    static public function getClassPath($class) : string
    {
        // Разделяем имя искомого класса на составляющие
        $aClassName = explode("_", strtolower($class));

        // Последний элемент полученного массива будет являться именем файла
        $sFileName = array_pop($aClassName);

        // Собираем путь к файлу
        // Если имя класса было передано без символа разделителя _
        $sPath = empty($aClassName) 
                    ? $sFileName . DIRECTORY_SEPARATOR 
                    : implode(DIRECTORY_SEPARATOR, $aClassName) . DIRECTORY_SEPARATOR; 

        // Добавляем имя файла
        $sPath .= $sFileName . ".php";
        
        // Возвращаем путь к файлу
        return $sPath;
    }
}
?>