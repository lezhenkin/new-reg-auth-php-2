<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class Core_Database_Pdo extends Core_DataBase
{
    /** Результат выполнения запроса
     * @var resource | NULL
     */
    protected $_result = NULL;

    /**
     * Представление результата запроса в виде ассоциативного массива либо объекта
     */
    protected $_fetchType = PDO::FETCH_OBJ;

    /**
     * Возвращает активное подключение к СУБД
     * @return resource
     */
    public function getConnection()
    {
        $this->connect();

        return $this->_connection;
    }

    /**
     * Подключается к СУБД
     * @return boolean TRUE | FALSE
     */
    public function connect()
    {
        // Если подключение уже выполнено, ничего не делаем
        if ($this->_connection)
        {
            return TRUE;
        }
        $this->_config += array(
			'driverName' => 'mysql',
			'attr' => array(
				PDO::ATTR_PERSISTENT => FALSE
			)
		);

        // Подключаемся к СУБД
        try {
            // Адрес сервера может быть задан со значением порта
            $aHost = explode(":", $this->_config['host']);
            
            // Формируем строку источника подключения к СУБД
            $dsn = "{$this->_config['driverName']}:host={$aHost[0]}";

            // Если был указан порт
            !empty($aHost[1])
                && $dsn .= ";port={$aHost[1]}";

            // Указываем имя БД
            !is_null($this->_config['dbname'])
				&& $dsn .= ";dbname={$this->_config['dbname']}";
            
            // Кодировка
            $dsn .= ";charset={$this->_config['charset']}";

            // Подключаемся, и сохраняем подключение в экземпляре класса
            $this->_connection = new PDO(
				$dsn,
				$this->_config['user'],
				$this->_config['password'],
				$this->_config['attr']
			);
            
            // В случае ошибок будет брошено исключение
			$this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        }
        catch (PDOException $e)
        {
            throw new Exception("<p><strong>Ошибка при подключении к СУБД:</strong> {$e->getMessage()}</p>");
        }
        
        // Если ничего плохого не произошло
        return TRUE;
    }
    
    /**
     * Закрывает соединение с СУБД
     * @return self
     */
    public function disconnect()
    {
        $this->_connection = NULL;

        return $this;
    }
    
    /**
     * Устанавливает кодировку соединения клиента и сервера
     * @param string $charset указанное наименование кодировки, которое примет СУБД
     */
    public function setCharset($charset)
    {
        $this->connect();

		$this->_connection->exec('SET NAMES ' . $this->quote($charset));

		return $this;
    }
    
    /**
     * Экранирование строки для использования в SQL-запросах
     * @param string $unescapedString неэкранированная строка
     * @return string Экранированная строка
     */
    public function escape($unescapedString) : string
    {
        $this->connect();

		$unescapedString = addcslashes(strval($unescapedString), "\000\032");

		return $this->_connection->quote($unescapedString);
    }

    /**
     * Возвращает результат работы метода PDO::quote()
     * @return string
     */
    public function quote(string $value) : string
    {
        return $this->_connection->quote($value);
    }

    /**
     * Возвращает идентификатор последней вставленной записи в БД, если такой имеется
     * @return integer|string|NULL
     */
    public function lastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Устанавливает строку запроса, который будет выполнен позднее
     * @param string $query
     * @return object self
     */
    public function query($query)
    {
        // Переданную строку запроса сохраняем, чтобы её потом можно было просмотреть
        $this->_lastQuery = $query;

        // По умолчанию устанавливаем, что результат запроса хотим получать в виде объекта
        $this->_fetchType = PDO::FETCH_OBJ;

        return $this;
    }

    /**
     * Устанавливает тип представления данных в результате запроса в виде объекта
     * @return object self
     */
    public function asObject()
    {
        $this->_fetchType = PDO::FETCH_OBJECT;

        return $this;
    }

    /**
     * Устанавливает тип представления данных в результате запроса в виде ассоциативного массива
     * @return object self
     */
    public function asAssoc()
    {
        $this->_fetchType = PDO::FETCH_ASSOC;

        return $this;
    }

    /**
     * Выполняет запрос SELECT, возвращает результат выполнения
     * @return object PDOStatement
     */
    public function result() : PDOStatement
    {
        // Результат выполнения запроса сохраняем внутри объекта
        $this->_result = $this->_connection->query($this->_lastQuery, $this->_fetchType);

        // Определяем количество строк в результате запроса, сохраняем внутри объекта
        $this->_lastQueryRows = $this->_result->rowCount();

        return $this->_result;
    }

    /**
     * Устанавливает перечень полей для запроса SELECT
     * @param string|array $data = "*"
     * @return object self
     */
    public function select($data = "*")
    {
        // Устанавливаем в объекте тип выполняемого запроса как SELECT
        $this->getQueryType() != 0 && $this->setQueryType(0);

        // Если методу не был передан перечень полей, очищаем все возможно установленные ранее поля
        if ($data == "*")
        {
            $this->clearSelect();
        }

        // Сохраняем поля
        try {
            // Если перечень полей был передан в виде строки
            if (is_string($data))
            {
                // Добавляем их к массиву в объекте
                $this->_select[] = $data;
            }
            // Если был передан массив, его нужно интерпретировать как указание имени поля и его псевдонима в запросе
            elseif (is_array($data))
            {
                // Если в переданном массиве не два элемента, это ошибка
                if (count($data) != 2)
                {
                    throw new Exception("<p>При передаче массива в качестве аргумента методу " . __METHOD__ . "() число элементов этого массива должно быть равным двум</p>");
                }
                // Если элементы переданного массива не являются строками, это ошибка
                elseif (!is_string($data[0]) || !is_string($data[1]))
                {
                    throw new Exception("<p>При передаче массива в качестве аргумента методу " . __METHOD__ . "() его элементы должны быть строками</p>");
                }
                // Если ошибок нет, сохраняем поля в массиве внутри объекта
                else
                {
                    // Имена полей экранируем
                    $this->_select[] = $this->quoteColumnNames($data[0]) . " AS " . $this->quoteColumnNames($data[1]);
                }
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }
        
        return $this;
    }

    /**
     * Очищает перечень полей для оператора SELECT
     * @return object self
     */
    public function clearSelect()
    {
        $this->_select = [];

        return $this;
    }

    /**
     * Устанавливает имя таблицы для оператора SELECT
     * @param string $from
     * @return object self
     */
    public function from(string $from)
    {
        try {

            if (!is_string($from))
            {
                throw new Exception("<p>Методу " . __METHOD__ . "() нужно передать имя таблицы для запроса</p>");
            }
            
            // Экранируем данные
            $this->_from = $this->quoteColumnNames($from);
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Сохраняет перечень условий для оператора WHERE в SQL-запросе
     * @param string $field
     * @param string $condition
     * @param string $value
     * @return object self
     */
    public function where(string $field, string $condition, $value)
    {
        try {
            if (empty($field) || empty($condition))
            {
                throw new Exception("<p>Методу " . __METHOD__ . "() обязательно нужно передать значения имени поля и оператора сравнения</p>");
            }

            // Экранируем имена полей и значения, которые будут переданы оператору WHERE
            $this->_where[] = $this->quoteColumnNames($field) . " " . $condition . " " . $this->_connection->quote($value);
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Очищает массив условий отбора для оператора WHERE
     * @return object self
     */
    public function clearWhere()
    {
        $this->_where = [];

        return $this;
    }

    /**
     * Устанавливает имя таблицы для оператора INSERT
     * @param string $tableName
     * @return object self
     */
    public function insert(string $tableName)
    {
        // Экранируем имя таблицы
        $this->_tableName = $this->quoteColumnNames($tableName);

        // Устанавливаем тип запроса INSERT
        $this->_queryType = 1;

        return $this;
    }

    /**
     * Устанавливает перечень полей для оператора INSERT
     * @return object self
     */
    public function fields()
    {
        try {
            // Если не было передано перечня полей
            if (empty(func_get_args()))
            {
                throw new Exception("Метод " . __METHOD__ . "() нельзя вызывать без параметров. Нужно передать перечень полей либо в виде строки, либо в виде массива");
            }

            // Сохраняем перечень полей в переменную
            $mFields = func_get_arg(0);

            // Если передан массив
            if (is_array($mFields))
            {
                // Просто сохраняем его
                $this->_fields = $mFields;
            }
            // Если передана строка
            elseif (is_string($mFields))
            {
                // Разбираем её, полученный массив сохраняем
                $this->_fields = explode(',', $mFields);
            }
            // В ином случае будет ошибка
            else
            {
                throw new Exception("Метод " . __METHOD__ . "() ожидает перечень полей либо в виде строки, либо в виде массива");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Устанавливает перечень значений, которые будут переданы оператору INSERT
     * @return object self
     */
    public function values()
    {
        try {
            // Если значения не переданы, это ошибка
            if (empty(func_get_args()))
            {
                throw new Exception("Метод " . __METHOD__ . "() нельзя вызывать без параметров. Нужно передать перечень значений либо в виде строки, либо в виде массива");
            }

            // Сохраняем переденные значения в переменную
            $mValues = func_get_arg(0);

            // Если был передан массив
            if (is_array($mValues))
            {
                // Просто сохраняем его
                $this->_values[] = $mValues;
            }
            // Если была передана строка
            elseif (is_string($mValues))
            {
                // Разбираем её, полученный массив сохраняем в объекте
                $this->_values[] = explode(',', $mValues);
            }
            // В ином случае будет ошибка
            else
            {
                throw new Exception("Метод " . __METHOD__ . "() ожидает перечень значений либо в виде строки, либо в виде массива");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Выполняет SQL-запрос к СУБД
     */
    public function execute() : PDOStatement | NULL
    {
        // Результат запроса будет представлен в виде объекта
        $this->_fetchType = PDO::FETCH_OBJ;

        // Пустая строка для SQL-запроса
        $sQuery = "";

        // Строка оператора WHERE
        $sWhere = " WHERE ";

        // Сначала собираем строку для оператора WHERE
        foreach ($this->_where as $index => $sWhereRow)
        {
            // Для каждого из сохраненного массива для оператора WHERE формируем строку
            $sWhere .= (($index) ? " AND" : "") . " " . $sWhereRow;
        }

        // Создаем данные, которые вернем в ответ на вызов
        $return = NULL;

        // Пробуем выполнить запрос
        try {

            // В зависимости от типа запроса
            switch ($this->getQueryType())
            {
                // SELECT
                case 0:
                    $sQuery .= "SELECT " . implode(", ", $this->_select) . " FROM {$this->_from}" . $sWhere;

                    $return = $this->query($sQuery)->result();
                break;
                
                // INSERT
                case 1:
                    /**
                     * Здесь мы воспользуемся механизмом подготовки запроса от PDO
                     * https://www.php.net/manual/ru/pdo.prepared-statements.php
                     */
                    $sPseudoValues = "(";
                    $sFields = "(";

                    foreach ($this->_fields as $index => $sField)
                    {
                        $sPseudoValues .= (($index) ? "," : "") . "?";
                        $sFields .= (($index) ? "," : "") . $this->quoteColumnNames($sField);
                    }

                    $sPseudoValues .= ")";
                    $sFields .= ")";

                    $sQuery .= "INSERT INTO " . $this->_tableName . " " . $sFields . " VALUES " . $sPseudoValues;

                    $stmt = $this->getConnection()->prepare($sQuery);
                    
                    foreach ($this->_values as $aValues)
                    {
                        for ($i = 1; $i <= count($aValues); ++$i)
                        {
                            $stmt->bindParam($i, $aValues[$i - 1]);
                        }

                        $stmt->execute();
                    }

                    $return = $stmt;

                break;
            }
            
            // Сохраняем строку запроса в объекте
            $this->_lastQuery = $sQuery;

        }
        catch (PDOException $e)
        {
            throw new Exception($e->getMessage());
        }

        return $return;
    }
}
?>