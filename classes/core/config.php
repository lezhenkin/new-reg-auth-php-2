<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Возвращает параметры конфигурации чего-либо
 */
class Core_Config
{
    // Экземпляр класса
    static protected $_instance = NULL;

    // Загруженные параметры
    protected $_values = [];

    /**
	 * Возвращает последний компонент имени из указанного пути
     * https://www.php.net/manual/ru/function.basename
	 * @param string $name
	 * @return string
	 */
	protected function _correctName($name) : string
	{
		return basename(strtolower($name));
	}

    /**
     * Возвращает и при необходимости создает экзепляр класса
     * @return object Core_Config
     */
    static public function instance()
    {
        // Если экземпляр класса ранее уже был создан, его и возвращаем
        if (!is_null(static::$_instance))
        {
            return static::$_instance;
        }

        // Создаем экземпляр класса, и сохраняем его здесь же
        static::$_instance = new static();

        // Возвращем вызову экземпляр класса
        return static::$_instance;
    }
    
    /**
	 * Получает параметры чего-либо из файла на сервере по запрошенному имени файла, к примеру Core_Database подключит classes/core/config/database.php
	 * @param string $name
	 * @return mixed Config | NULL
	 */
	public function get($name)
	{
		$name = $this->_correctName($name);

        // Если ранее не запрашивались параметры с таким именем
		if (!isset($this->_values[$name]))
		{
            // Получаем путь к нужному файлу
			$sPath = $this->getPath($name);

            // Если такой путь существует
			$this->_values[$name] = is_file($sPath)
				? require_once($sPath)
				: NULL;
		}

		return $this->_values[$name];
	}

    /**
	 * Получает путь к файлу с параметрами
	 * @param string $name
	 * @return string
	 */
	public function getPath($name)
	{
		// Разбираем строку с переданным именем на составляющие
        $aConfig = explode('_', $name);

        // Последним элементом будет имя файла
		$sFileName = array_pop($aConfig);

        // Собираем путь к файлу
		$path = Core::$classesPath;
		$path .= implode(DIRECTORY_SEPARATOR, $aConfig) . DIRECTORY_SEPARATOR;
		$path .= 'config' . DIRECTORY_SEPARATOR . $sFileName . '.php';
        
		return $path;
	}
}
?>