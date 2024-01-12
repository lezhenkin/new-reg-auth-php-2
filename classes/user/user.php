<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Класс пользователя клиентского раздела сайта
 * Объект класса может создаваться как для существующих пользователей, так и для несуществующих с целью их регистрации
 */
class User
{
    /**
     * Информация о пользователе (логин, пароль, и т.п.)
     * @var array
     */
    protected $_data = [];

    /**
     * Результируйщий объект запроса к БД
     * @var stdClass|NULL
     */
    protected $_user = NULL;

    /**
     * Разрешенные для чтения и записи поля таблицы пользователей
     * @var array
     */
    protected $_allowedProperties = [
        'id',
        'login',
        'email',
        'registration_date',
        'active'
    ];

    /**
     * Запрещенные для чтения и записи поля таблицы пользователей
     * @var array
     */
    protected $_forbiddenProperties = [
        'password',
        'deleted'
    ];

    /**
     * Имена полей таблицы в БД
     * @var array
     */
    protected $_columnNames = [];

    /**
     * Строка для действия регистрации
     * @param const 
     */
    public const ACTION_SIGNUP = 'sign-up';
    
    /**
     * Строка для действия регистрации
     * @param const 
     */
    public const ACTION_SIGNIN = 'sign-in';

    /**
     * Строка для действия выхода из системы
     * @param const 
     */
    public const ACTION_LOGOUT = 'exit';

    /**
     * Получает данные о пользователе сайте
     * @return mixed self|NULL
     */
    protected function _getUserData()
    {
        $return = NULL;
        
        // Если внутри объекта нет информации о пользователе, пробуем получить её из сессии
        if (is_null($this->_user))
        {
            if (!empty($_SESSION['password']))
            {
                $sPassword = strval($_SESSION['password']);
                $sValue = strval((!empty($_SESSION['email'])) ? $_SESSION['email'] : $_SESSION['login'] );

                $stmt = $this->getByLoginOrEmail($sValue);
                
                if (!is_null($stmt))
                {
                    $this->_user = $stmt->fetch();

                    $return = $this;
                }
            }
        }
        else
        {
            $return = $this;
        }

        return $return;
    }
    
    /**
     * Проверяет, не был ли авторизован пользователь ранее
     * @param string $value логин или адрес электропочты
     * @return boolean TRUE|FALSE
     */
    protected function _checkCurrent(string $value) : bool
    {
        $bIsAuth = FALSE;

        // Если в сессии сохранены логин или электропочта, а функции переданы значения для проверки, и они совпадают с теми, что хранятся в сессии
        if ((!empty($_SESSION['login']) || !empty($_SESSION['email'])) && ($_SESSION['login'] === $value || $_SESSION['email'] === $value))
        {
            // Пользователь авторизован
            $bIsAuth = TRUE;
        }
        // Если есть попытка подмены данных в сессии
        elseif ((!empty($_SESSION['login']) || !empty($_SESSION['email'])) && $_SESSION['login'] !== $value && $_SESSION['email'] !== $value)
        {
            // Стираем данные из сессии
            unset($_SESSION['login']);
            unset($_SESSION['email']);
            unset($_SESSION['password']);

            // Останавливаем работу скрипта
            die("<p>Несоответствие данных авторизации сессии. Работа остановлена</p>");
        }

        return $bIsAuth;
    }

    /**
     * Конструктор класса
     * @param int $id = 0
     */
    public function __construct(int $id = 0)
    {
        // Сразу же из базы данных получаем перечень имен полей таблицы
        $this->getColumnNames();
    }

    /**
     * Получает перечень имен полей таблицы из БД
     * @return self
     */
    public function getColumnNames()
    {
        $oCore_Database = Core_Database::instance();
        
        $this->_columnNames = $oCore_Database->getColumnNames('users');

        return $this;
    }

    /**
     * Получает информацию об авторизованном пользователе
     * @return mixed self | NULL если пользователь не авторизован
     */
    public function getCurrent()
    {
        $return = NULL;

        /**
         * Информация о пользователе, если он авторизован, хранится в сессии
         * Поэтому нужно просто проверить, имеется ли там нужная информация
         * Если в сессии её нет, значит пользователь не авторизован
         */
        (!empty($_SESSION['login']) 
            && !empty($_SESSION['email']) 
            && !empty($_SESSION['password']))
                && $return = $this->_getUserData();
        
        // Возвращаем результат вызову
        return $return;
    }

    /**
     * Устанавливает в сесии параметры пользователя, прошедшего авторизацию
     * @return object self
     */
    public function setCurrent()
    {
        $_SESSION['login'] = $this->_user->login;
        $_SESSION['email'] = $this->_user->email;
        $_SESSION['password'] = $this->_user->password;

        return $this;
    }

    /**
     * Завершает сеанс пользователя в системе
     * @return object self
     */
    public function unsetCurrent()
    {
        // Уничтожение данных о пользователе в сессии
        unset($_SESSION['login']);
        unset($_SESSION['email']);
        unset($_SESSION['password']);

        header("Refresh:0;"); die();

        return NULL;
    }

    /**
     * Обрабатывает данные, которыми пользователь заполнил форму
     * @param array $post
     */
    public function processUserData(array $post)
    {
        $aReturn = [
            'success' => FALSE,
            'message' => "При обработке формы произошла ошибка",
            'data' => [],
            'type' => static::ACTION_SIGNIN
        ];
        
        // Если не передан массив на обработку, останавливаем работу сценария
        if (empty($post))
        {
            die("<p>Для обработки пользовательских данных формы должен быть передан массив</p>");
        }
        
        // Если в массиве отсутствуют данные о типе заполненной формы, останавливаем работу сценария
        if (empty($post[static::ACTION_SIGNIN]) && empty($post[static::ACTION_SIGNUP]))
        {
            die("<p>Метод <code>User::processUserData()</code> должен вызываться только для обработки данных из форм авторизации или регистрации</p>");
        }

        // Флаг регистрации нового пользователя
        $bRegistrationUser = !empty($post[static::ACTION_SIGNUP]);

        // Логин и пароль у нас должны иметься в обоих случаях
        $sLogin = strval(htmlspecialchars(trim($_POST['login'])));
        $sPassword = strval(htmlspecialchars(trim($_POST['password'])));

        // А вот электропочта и повтор пароля будут только в случае регистрации
        if ($bRegistrationUser)
        {
            $aReturn['type'] = static::ACTION_SIGNUP;

            $sEmail = strval(htmlspecialchars(trim($_POST['email'])));
            $sPassword2 = strval(htmlspecialchars(trim($_POST['password2'])));

            // Проверяем данные на ошибки
            if ($this->validateEmail($sEmail))
            {
                // Логин и пароли не могут быть пустыми
                if (empty($sLogin))
                {
                    $aReturn['message'] = "Поле логина не было заполнено";
                    $aReturn['data'] = $post;
                }
                elseif (empty($sPassword))
                {
                    $aReturn['message'] = "Поле пароля не было заполнено";
                    $aReturn['data'] = $post;
                }
                // Пароли должны быть идентичны
                elseif ($sPassword !== $sPassword2)
                {
                    $aReturn['message'] = "Введенные пароли не совпадают";
                    $aReturn['data'] = $post;
                }
                // Если логин не уникален
                elseif ($this->isValueExist($sLogin, 'login'))
                {
                    $aReturn['message'] = "Указанный вами логин ранее уже был зарегистрирован";
                    $aReturn['data'] = $post;
                }
                // Если email не уникален
                elseif ($this->isValueExist($sEmail, 'email'))
                {
                    $aReturn['message'] = "Указанный вами email ранее уже был зарегистрирован";
                    $aReturn['data'] = $post;
                }
                // Если все проверки прошли успешно, можно регистрировать пользователя
                else
                {
                    /**
                     * Согласно документации к PHP, мы для подготовки пароля пользователя к сохранению в БД
                     * будем использовать функцию password_hash() https://www.php.net/manual/ru/function.password-hash
                     * Причем, согласно рекомендации, начиная с версии PHP 8.0.0 не нужно указывать соль для пароля. Значит, и не будем
                     */
                    // Хэшируем пароль
                    $sPassword = password_hash($sPassword, PASSWORD_BCRYPT);
                    
                    $this->login = $sLogin;
                    $this->password = $sPassword;
                    $this->email = $sEmail;
                    $this->save();

                    if (Core_Database::instance()->lastInsertId())
                    {
                        $aReturn['success'] = TRUE;
                        $aReturn['message'] = "Пользователь с логином <strong>{$sLogin}</strong> и email <strong>{$sEmail}</strong> успешно зарегистрирован.";
                        $aReturn['data']['user_id'] = Core_Database::instance()->lastInsertId();
                    }
                }
            }
            else
            {
                $aReturn['message'] = "Указанное значение адреса электропочты не соответствует формату";
                $aReturn['data'] = $post;
            }
        }
        // Если пользователь авторизуется
        else
        {
            // Проверяем, не был ли пользователь ранее авторизован
            if ($this->_checkCurrent($sLogin))
            {
                $aReturn['success'] = TRUE;
                $aReturn['message'] = "Вы ранее уже авторизовались на сайте";
            }
            // Если авторизации не было
            else 
            {
                // Если не передан пароль
                if (empty($sPassword))
                {
                    $aReturn['message'] = "Поле пароля не было заполнено";
                    $aReturn['data'] = $post;
                }
                else 
                {
                    // Ищем соответствие переданной информации в БД
                    $stmt = $this->getByLoginOrEmail($sLogin);
                    
                    // Если были найдены записи
                    if (!is_null($stmt))
                    {
                        // Получаем объект с данными о пользователе
                        $oUser = $this->_user = Core_Database::instance()->result()->fetch();
                        
                        // Проверяем пароль пользователя
                        // Если хэш пароля совпадает
                        if ($this->checkPassword($sPassword, $oUser->password))
                        {
                            // Авторизуем пользователя
                            $this->setCurrent();

                            $aReturn['success'] = TRUE;
                            $aReturn['message'] = "Вы успешно авторизовались на сайте";
                            $aReturn['data'] = $post;
                            $aReturn['data']['user_id'] = $oUser->id;
                        }
                        else
                        {
                            $aReturn['message'] = "Для учетной записи <strong>{$sLogin}</strong> указан неверный пароль";
                            $aReturn['data'] = $post;
                        }
                    }
                }
            }
        }

        return $aReturn;
    }

    /**
     * Ищет в БД запись по переданному значению полей login или email
     * @param string $value
     * @return object PDOStatement|NULL
     */
    public function getByLoginOrEmail(string $value) : PDOStatement | NULL
    {
        // Определяем тип авторизации: по логину или адресу электропочты
        $sType = NULL;
        $sType = match($this->validateEmail($value)) {
                    TRUE => 'email',
                    FALSE => 'login'
        };

        // Выполняем запрос SELECT
        $oCore_Database = Core_Database::instance();
        $oCore_Database->select()
            ->from('users')
            ->where($sType, '=', $value)
            ->where('deleted', '=', 0)
            ->where('active', '=', 1);
        
        $stmt = $oCore_Database->execute();

        // Если такой пользователь есть в БД, вернем объект с результатом запроса
        return ($oCore_Database->getRowCount() > 0) ? $stmt : NULL;
    }

    /**
     * Проверяет пароль пользователя, совпадает ли он с хранимым в БД
     * @param string $password пароль пользователя
     * @param string $hash хэш пароля пользователя из БД
     * @return boolean TRUE|FALSE
     */
    public function checkPassword(string $password, string $hash) : bool
    {
        /**
         * Согласно документации к PHP, мы для подготовки пароля пользователя к сохранению в БД
         * мы использовали функцию password_hash() https://www.php.net/manual/ru/function.password-hash
         * Теперь для проверки пароля для авторизации нам нужно использовать функцию password_verify()
         * https://www.php.net/manual/ru/function.password-verify.php
         */
        return password_verify($password, $hash);
    }

    /**
     * Проверяет правильность адреса электронной почты
     * @param string $email
     * @return TRUE | FALSE
     */
    public function validateEmail(string $email) : bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Проверяет уникальность логина в системе
     * @param string $value
     * @param string $field
     * @return TRUE | FALSE
     */
    public function isValueExist($value, $field) : bool
    {
        // Подключаемся к СУБД
        $oCore_Database = Core_Database::instance();
        $oCore_Database->clearSelect()
            ->clearWhere()
            ->select()
            ->from('users')
            ->where($field, '=', $value)
            ->where('deleted', '=', 0);

        // Выполняем запрос
        try {
            $stmt = $oCore_Database->execute();
        }
        catch (PDOException $e)
        {
            die("<p><strong>При выполнении запроса произошла ошибка:</strong> {$e->getMessage()}</p>");
        }
        
        // Если логин уникален, в результате запроса не должно быть строк
        return $oCore_Database->getRowCount() !== 0;
    }

    /**
     * Сохраняет информацию о пользователе
     * @return object self
     */
    public function save()
    {
        $oCore_Database = Core_Database::instance();
        $oCore_Database->insert('users')
            ->fields(['login', 'password', 'email'])
            ->values([
                $this->_data['login'], 
                $this->_data['password'], 
                $this->_data['email']
            ]);
        
        $stmt = $oCore_Database->execute();

        return $this;
    }
    
    /**
     * Магический метод для установки значений необъявленных свойств класса
     * @param string $property
     * @param mixed $value
     */
    public function __set(string $property, $value)
    {
        $this->_data[$property] = $value;
    }

    /**
     * Магический метод для получения значения необъявленного свойства класса
     * Вернет значение из запрошенного поля таблицы, если оно разрешено в массиве $_allowedProperties
     * @return mixed string|NULL
     */
    public function __get(string $property) : string | NULL
    {
        return (in_array($property, $this->_allowedProperties) ? $this->_user->$property : NULL);
    }

}
?>