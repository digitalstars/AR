<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\InterfaceDB\SmartList\SmartList;
use DigitalStars\InterfaceDB\SmartList\SmartListItem;
use DigitalStars\SimpleSQL\Components\From;
use DigitalStars\SimpleSQL\Components\Join;
use DigitalStars\SimpleSQL\Components\Where;
use DigitalStars\SimpleSQL\Insert;
use DigitalStars\SimpleSQL\Select;
use DigitalStars\SimpleSQL\Update;

/**
 * @property-read  int id
 */
abstract class Module implements SmartListItem {

    /*    protected static array $FIELDS = [                        // Описание типов магических свойств
            'id' => [                                               // Ключ - имя магического свойства
                'type' => 'int',                                    // Тип данных [int, string, bool, double]
                'default' => null,                                  // Дефолтное значение
                'access_modify' => false,                           // Доступ на изменение свойства (поисле создания записи в БД)
                'is_required' => false                              // Обязательное свойство
            ],
            'user' => [                                             // Ключ - имя магического свойства
                'type' => 'System\User',                            // Тип данных может быть задан как полное название класса (с пространством имён)
                'default' => 1,                                     // Дефолтное значение
                'access_modify' => true,                            // Доступ на изменение свойства (поисле создания записи в БД)
                'is_required' => true                               // Обязательное свойство
            ],
            'order' => [                                            // Ключ - имя магического свойства
                'type' => Order::class,                             // Можно указать имя класса так
                'default_func' => [                                 // Дефолтное значение, инициализируемое функцией
                    'func' => ['__this', 'generateOrder'],          // Описание функции, которая будет вызвана (если нужно вызвать метод класса)
                    'values' => [4072081]                           // Список переменных, передаваемых в метод в виде массива
                ],
                'access_modify' => true,                            // Доступ на изменение свойства (поисле создания записи в БД)
                'is_required' => false,                             // Обязательное свойство
                'db' => 'user_order_id'                             // Имя поля в БД, если отличается от имени свойства
            ],
            'time' => [                                             // Ключ - имя магического свойства
                'type' => Time::class,                              // Можно указать имя класса так
                'default_func' => [                                 // Дефолтное значение, инициализируемое функцией
                    'func' => [Time::class, 'create'],              // Описание функции, которая будет вызвана
                    'values' => [4072081]                           // Список переменных, передаваемых в метод в виде массива
                ],
                'access_modify' => true,                            // Доступ на изменение свойства (поисле создания записи в БД)
                'is_required' => false                              // Обязательное свойство
            ],
        ];*/

    protected static array $FIELDS = [
        'id' => [
            'type' => 'int',
            'default' => null,
            'access_modify' => false,
            'is_required' => false,
            'db' => 'id'
        ]
    ];

    // Магические свойства и обработка значений по умолчанию
    public function __construct(?int $id = null) {
        $this->id = $id;
    }

    public function __get($name) {
        $value = $this->getUserField($name);

        $is_get_default = false;
        if (is_null($value)) {
            $value = $this->fieldGetDefaultValue($name);
            if (!is_null($value))
                $is_get_default = true;
        }

        $value = $this->fieldValidateType($name, $value);

        if ($is_get_default)
            $this->setUserField($name, $value);

        return $value;
    }

    public function __set($name, $value) {
        $value = $this->fieldValidateType($name, $value);

        if ($this->getUserField($name) === $value)
            return;

        $this->setUserField($name, $value);
    }

    public function __isset($name) {
        return isset(static::$FIELDS[$name]);
    }

    public function __destruct() {
        if ($this->isModeModify())
            $this->runUpdate();
    }

    public function isValid(): bool {
        if ($this->isSetId()) {
            try {
                $this->initDbInfo();
            } catch (Exception $e) {
                if ($e->getMessage() === 'DBInfo not found')
                    return false;
                throw $e;
            }
        } else {
            try {
                foreach (static::$FIELDS as $name => $info) {
                    if (!empty($info['is_required']))
                        $this->$name;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function isSetId(): bool {
        return !is_null($this->id);
    }

    public function clearDbInfo() {
        if (!$this->is_load_data_from_db)
            return $this;

        $this->info = [];
        $this->is_load_data_from_db = false;
        return $this;
    }

    public function isLoadDataFromDB(): bool {
        return $this->is_load_data_from_db;
    }

    public function getModifyFields() {
        return array_keys($this->modify_fields);
    }

    public function runUpdate() {
        if (!$this->isModeModify())
            throw new Exception('Update is not access');

        if (empty($this->update_fields))
            return;

        $sql = Update::create()
            ->setFrom(static::getFrom())
            ->setWhere(Where::create('id = ?i', [$this->id]))
            ->setLimit(1);

        foreach ($this->update_fields as $name => $data)
            $sql->addSet($data[0], $data[1], $data[2]);

        Main::exec($sql->getSql());
        $this->update_fields = [];
    }

    public function createItem() {
        if (!$this->isModeCreate())
            throw new Exception('Create is not access');

        $sql = Insert::create()
            ->setFrom(static::getFrom());

        $values = [];
        foreach (static::$FIELDS as $name => $info) {
            if ($name === 'id')
                continue;

            $is_object = static::fieldIsObject($name);
            $sql->addField($this->getFieldNameFromDB($name), $this->TypeToPlaceholder($is_object ? 'int' : $info['type']));
            $values[] = $this->$name;
        }
        $sql->addValues($values);

        Main::exec($sql->getSql());
        $this->id = Main::getPDO()->lastInsertId();

        $this->is_load_data_from_db = true;
    }

    public static function create(?int $id = null) {
        return new static($id);
    }

    public static function getSql(): Select {
        return Select::create()
            ->setSelect(
                array_combine(
                    array_column(static::getSelectFields(), 'query_name'),
                    array_column(static::getSelectFields(), 'db_name')))
            ->setFrom(static::getFrom())
            ->setLimit(1);
    }

    public static function getJoin(string $type = 'INNER'): Join {
        return Join::create($type, static::getFrom());
    }

    /** Test
     * @param self $filter
     * @return $this
     */
    public static function createFromSelf(self $filter): static {
        $sql = $filter->setInSqlModifyFields();
        return static::createFromSql($sql);
    }

    public static function createListFromSelf(self $filter): SmartList {
        $sql = $filter->setInSqlModifyFields();
        return static::getListFromSQL($sql);
    }

    protected function getUserField($name) {
        if ($name === 'id')
            return $this->id;

        $this->initDbInfo();
        return $this->info[$name] ?? null;
    }

    protected function setUserField($name, $value) {
        $this->initDbInfo();

        if (!$this->isAccessModifyField($name))
            throw new Exception("$name Is not access modify");

        if ($name === 'id') {
            throw new Exception('ID is not modify');
        }

        $old_value = $this->info[$name] ?? null;
        $this->info[$name] = $value;

        if ($this->isModeModify())
            $this->updateDB($name, $value, $old_value);
        $this->modify_fields[$name] = true;
    }

    /** Если сейчас режим СОЗДАНИЯ записи в БД
     * @return bool
     */
    protected function isModeCreate(): bool {
        return !$this->isSetId();
    }

    /** Если сейчас режим ОБНОВЛЕНИЯ данных в БД
     * @return bool
     */
    protected function isModeModify(): bool {
        return $this->isSetId();
    }

    /** Можно ли изменить данное поле в текущий момент (можно менять поле в БД или режим создания записи в БД, а не обновления)
     * @param string $name
     * @return bool
     */
    protected function isAccessModifyField(string $name): bool {
        return $this->isModeCreate() || (isset(static::$FIELDS[$name]['access_modify']) && static::$FIELDS[$name]['access_modify']);
    }

    protected function initDbInfo() {
        if ($this->is_load_data_from_db || is_null($this->id))
            return;

        $sql = static::getSql();
        $alias = static::getFrom()->getAlias();

        $sql->getWhere()->w_and($alias . '.id = ?i', [$this->id]);
        $this->parseDbData(Main::query($sql->getSql())?->fetch());
    }

    protected function parseDbData(array|null|bool $tmp_info = null) {
        if (!$tmp_info)
            throw new Exception('DBInfo not found');

        foreach (static::getSelectFields() as $name => $info) {
            $is_object = static::fieldIsObject($name);
            if ($is_object)
                $this->info[$name] = call_user_func(
                    [static::$FIELDS[$name]['type'], 'create'], $tmp_info[$info['query_name']]
                );
            else
                $this->info[$name] = $tmp_info[$info['query_name']];
        }

        if (is_null($this->id)) {
            if (empty($this->info['id']))
                throw new Exception('DBInfo not found');

            $this->id = $this->info['id'];
        }

        $this->is_load_data_from_db = true;
    }

    protected function updateDB(string $name, $value, $old_value) {
        $is_object = static::fieldIsObject($name);
        $this->update_fields[$name] = [
            $this->getFieldNameFromDB($name),
            $this->TypeToPlaceholder($is_object ? 'int' : static::$FIELDS[$name]['type']),
            $this->getRawValueFromDB($value, $is_object)
        ];
    }

    protected function getRawValueFromDB($value, bool $is_object) {
        if (is_null($value))
            return null;
        if ($is_object) {
            if ($value instanceof Module)
                return $value->getId();
            else
                throw new Exception('Update type not found');
        } else
            return $value;
    }

    protected function getFieldNameFromDB($field): string {
        if (isset(static::$FIELDS[$field]['db']))
            return static::$FIELDS[$field]['db'];
        if (static::fieldIsObject($field))
            return $field . '_id';
        return $field;
    }

    protected static function getListFromSQL(Select $sql): SmartList {
        $result = new SmartList(static::class);

        $tmp_info_q = Main::query($sql->setLimit()->getSql());
        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = static::create();
            $item->parseDbData($tmp_info);
            $result[] = $item;
        }

        return $result;
    }

    protected static function createFromSql(Select $sql) {
        $tmp_info = Main::query($sql->setLimit(1)->getSql())->fetch();

        if (empty($tmp_info))
            return null;

        $item = static::create();
        $item->parseDbData($tmp_info);

        return $item;
    }

    protected static string $SQL_FROM = '';
    protected static string $SQL_ALIAS = '';

    protected static function getSelectFields(): array {
        if (!empty(static::$cache_select_fields))
            return static::$cache_select_fields;

        $alias = static::getFrom()->getAlias();
        foreach (static::$FIELDS as $name => $info) {
            $db_name = $info['db'] ?? $name;
            static::$cache_select_fields[$name] = [
                'name' => $name,
                'db_name' => "$alias.$db_name",
                'query_name' => "{$alias}_$db_name",
                'is_object' => !in_array($info['type'], ['bool', 'int', 'string', 'double'], true)
            ];
        }

        return static::$cache_select_fields;
    }

    protected static function getFrom(): From {
        if (!isset(self::$FROM))
            self::$FROM = From::create(static::$SQL_FROM, static::$SQL_ALIAS);

        return self::$FROM;
    }

    protected static function fieldIsObject($name) {
        return static::getSelectFields()[$name]['is_object'];
    }

    private array $info = [];
    private ?int $id;
    private bool $is_load_data_from_db = false;
    private array $modify_fields = [];
    private static From $FROM;
    private static array $cache_select_fields = [];

    private array $update_fields = [];

    private function fieldGetDefaultValue(string $name) {
        $value = null;
        if (isset(static::$FIELDS[$name]['default'])) {
            $value = static::$FIELDS[$name]['default'];
        } else if (isset(static::$FIELDS[$name]['default_func'])) {
            $func = static::$FIELDS[$name]['default_func']['func'];
            if ($func[0] === '__this')
                $func[0] = $this;

            if (empty(static::$FIELDS[$name]['default_func']['values'])) {
                $value = $func();
            } else {
                $value = call_user_func_array($func, static::$FIELDS[$name]['default_func']['values']);
            }
        }
        return $value;
    }

    private function fieldValidateType(string $name, $value) {
        if (static::$FIELDS[$name]['type'] === 'bool') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (bool)$value;
            else
                return is_null($value) ? null : (bool)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'int') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (int)$value;
            else
                return is_null($value) ? null : (int)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'double') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (double)$value;
            else
                return is_null($value) ? null : (double)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'string') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (string)$value;
            else
                return is_null($value) ? null : (string)$value;
        }

        if (!empty(static::$FIELDS[$name]['type'])) {
            $class_name = static::$FIELDS[$name]['type'];

            if (is_null($value) && !empty(static::$FIELDS[$name]['is_required']))
                throw new Exception("$name Not access NULL");

            if (!is_null($value) && !is_a($value, $class_name))
                throw new Exception("$name Not object class " . static::$FIELDS[$name]['type']);

            return $value;
        }

        throw new Exception("$name Not found in " . get_class($this));
    }

    private function TypeToPlaceholder(string $type) {
        if ($type === 'int' || $type === 'bool')
            return '?i';
        if ($type === 'double')
            return '?d';
        if ($type === 'string')
            return '?s';
        return 'null';
    }

    private function setInSqlModifyFields(Select $sql = null): Select {
        if (empty($sql))
            $sql = static::getSql();

        foreach ($this->getModifyFields() as $field) {
            $field_info = static::$FIELDS[$field];
            $is_object = static::fieldIsObject($field);
            $db_field = $this->getFieldNameFromDB($field);
            $placeholder = $this->TypeToPlaceholder($is_object ? 'int' : $field_info['type']);
            $raw_value = $this->getRawValueFromDB($this->$field, $is_object);
            $sql->getWhere()->w_and("?f = $placeholder", [$db_field, $raw_value]);
        }

        return $sql;
    }
}
