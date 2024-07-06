<?php

namespace DigitalStars\AR;

use JsonSerializable;
use DigitalStars\AR\Field\FInt;
use DigitalStars\AR\Field\FLink;
use DigitalStars\AR\Field\Reflection;
use DigitalStars\AR\Field\WithoutType;
use DigitalStars\AR\Helpers\Store;
use DigitalStars\AR\Helpers\TableBase;
use DigitalStars\AR\Helpers\TableCache;
use DigitalStars\AR\SmartList\SmartList;
use DigitalStars\AR\SmartList\SmartListItem;
use DigitalStars\SimpleSQL\Components\From;
use DigitalStars\SimpleSQL\Components\Join;
use DigitalStars\SimpleSQL\Components\Where;
use DigitalStars\SimpleSQL\Delete;
use DigitalStars\SimpleSQL\Insert;
use DigitalStars\SimpleSQL\Select;
use DigitalStars\SimpleSQL\Update;

/**
 * @property-read  FInt id
 */
abstract class Table implements SmartListItem, JsonSerializable {
    use TableBase;

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
                'validate' => [                                     // Правила валидации. Может содержать несколько правил. Ниже примеры
                    'equal' => 'test_string',                       // Проверка на равенство
                    'not_equal' => 'test_string_2',                 // Проверка на неравенство
                    'in' => ['test_1', 'test_2', 'test_3'],         // Проверка на вхождение в список
                    'not_in' => ['test_4', 'test_5', 'test_6'],     // Проверка на отсутствие в списке
                    'compare' => ['>', 0],                          // Сравнение
                    'compare' => ['<', 100],                        // Сравнение
                    'compare' => ['<=', 100],                       // Сравнение
                    'compare' => ['>=', 0],                         // Сравнение
                    'preg' => '/^test_\d+$/',                       // Проверка на соответствие регулярному выражению
                    'func' => ['__this', 'validateCustomField']     // Проверка с помощью функции
                ]                                                   // Если валидация не пройдена, будет выброшено исключение 'Value invalid'
            ],
        ];*/

    protected static array $FIELDS = [
        'id' => [
            'type' => FInt::TYPE,
            'default' => null,
            'access_modify' => false,
            'is_required' => false,
            'db' => 'id',
//            'validate' => [
//                'equal' => 'test_string',
//                'not_equal' => 'test_string_2',
//                'in' => ['test_1', 'test_2', 'test_3'],
//                'not_in' => ['test_4', 'test_5', 'test_6'],
//                'compare' => ['>', 0],
//                'compare' => ['<', 100],
//                'compare' => ['<=', 100],
//                'compare' => ['>=', 0],
//                'preg' => '/^test_\d+$/',
//                'func' => ['__this', 'validateCustomField']
//            ]
        ]
    ];

    protected static string $SQL_FROM = '';
    protected static string $SQL_ALIAS = '';

    protected int|null $MAX_DEPTH = null;
    protected bool|null $IS_LAZY_UPDATE = null;

    protected static bool $IS_LOAD_DATA_DB_AFTER_CREATE = false; // Вытащить из БД данные после создания записи

    private Store $store;
    private Reflection $ref;
    protected From $FROM;
    private array $fields = [];

    // конструкторы
    private function __construct(?int $id = null) {
        $this->store = new Store();
        $this->store->info['id'] = $id;
        $this->fields['id'] = $this->createField('id', $this->store->info['id']);
        if ($id)
            TableCache::saveTableCache($this);
    }

    public static function create(?int $id = null) {
        if ($id)
            return TableCache::getTableCacheForClassnameAndId(static::class, $id) ?: new static($id);
        return new static($id);
    }

    // Магические методы

    public function __destruct() {
        if ($this->isModeModify())
            $this->updateItem();
    }

    public function __isset($name) {
        return isset(static::$FIELDS[$name]);
    }

    // Геттеры поля

    public function getFieldValue($name) {
        $value = $this->getFieldRaw($name);

        $is_get_default = false;
        if (is_null($value)) {
            $value = $this->getFieldDefaultValue($name);
            if (!is_null($value))
                $is_get_default = true;
        }

        if ($is_get_default)
            $this->setFieldRaw($name, $value);

        $result = $this->fields[$name] ?? null;
        if (is_null($result)) {
            $this->store->info[$name] = $value;
            $this->fields[$name] = $this->createField($name, $this->store->info[$name]);
        }

        return $this->store->info[$name];
    }

    private function createEmptyField(string $name) {
        if (empty($this->store->info[$name]))
            $this->store->info[$name] = null;
        $this->fields[$name] = $this->createField($name, $this->store->info[$name]);
    }

    private function getFieldRaw($name) {
        if ($this->isTableField($name)) {
            $result = $this->store->info[$name] ?? null;
            if (is_null($result)) {
                /** @var self $result */
                $result = call_user_func([static::$FIELDS[$name]['table'], 'create']);

                $this->store->info[$name] = $result;
                $field = $this->createField($name, $this->store->info[$name]);
                $this->fields[$name] = $field;

                if (!$this->isLoadDataFromDB())
                    $result->setParent($this);
            }

            return $result;
        }

        if ($name === 'id') {
            if (empty($this->fields['id'])) {
                $this->createEmptyField('id');
            }

            if (!$this->isSetId())
                $this->initDB();

            return $this->store->info[$name];
        }

        $this->initDB();

        return $this->store->info[$name] ?? null;
    }

    public function getFieldDefaultValue(string $name) {
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

    public function ref(): Reflection {
        return $this->ref;
    }

    private function setRef(Reflection $ref) {
        $this->ref = $ref;
    }

    // Сеттеры поля

    public function setField($name, $value): ?static {
        if (empty(static::$FIELDS[$name]))
            throw new Exception("Field $name is not found in: " . static::class);

        if ($value instanceof WithoutType)
            $value = $value->val;

        if (!$this->isAccessModifyField($name))
            throw new Exception("$name Is not access modify");

        if ($this->isTableField($name)) {
            if ($this->getFieldRaw($name)->id === $value->id)
                return $this;
        } else {
            if ($this->getFieldRaw($name) === $value)
                return $this;
        }

        $this->setFieldRaw($name, $value);
        return $this;
    }

    private function setFieldRaw($name, $value) {
        if (!$this->fieldValidateCustom($name, $value))
            throw new Exception('Value invalid');

        $this->initDB();

        $old_value = $this->store->info[$name] ?? null;

        if ($this->isSetCustomFilter() && $value instanceof self && $old_value instanceof self) {
            $value->FROM = $old_value->FROM;
        }

        $this->store->info[$name] = $value;
        if (empty($this->fields[$name]) || $value instanceof self) {
            $this->fields[$name] = $this->createField($name, $this->store->info[$name]);
        }

        /** @var WithoutType $field */
        $field = $this->fields[$name];

        if ($this->isModeModify() && $this->isAccessModifyField($name))
            $this->updateDB($name, $field->ref()->is_table ? $value : $field, $old_value);
        $this->addModifyFields($name);
    }

    protected function updateDB(string $name, WithoutType|Table $value, $old_value) {
        $ref = $value->ref();

        $this->store->update_fields[$name] = [
            $ref->name,
            $ref->placeholder,
            $value->raw()
        ];
        if (!$this->getIsLazyUpdate())
            $this->updateItem();
    }

    // Информация о Table

    public function getMaxDepth(): int {
        return is_null($this->MAX_DEPTH) ? Settings::getMaxDepth() : $this->MAX_DEPTH;
    }

    public function setMaxDepth(?int $max_depth = null): static {
        $this->MAX_DEPTH = $max_depth;
        return $this;
    }

    public function getIsLazyUpdate(): bool {
        return is_null($this->IS_LAZY_UPDATE) ? Settings::getIsLazyUpdate() : $this->IS_LAZY_UPDATE;
    }

    public function setIsLazyUpdate(?int $is_lazy_update = null): static {
        $this->IS_LAZY_UPDATE = $is_lazy_update;
        return $this;
    }

    public function isValid(): bool {
        if ($this->isSetId()) {
            try {
                $this->initDB();
            } catch (Exception $e) {
                if ($e->getMessage() === 'DBInfo not found')
                    return false;
                throw $e;
            }
        } else {
            try {
                foreach (static::$FIELDS as $name => $info) {
                    $field = $this->getField($name);
                    if (is_null($field->val) && $field->ref()->is_required)
                        return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function getId(): ?int {
        return $this->store->info['id'] ?? null;
    }

    public function isSetId(): bool {
        return isset($this->store->info['id']) && !is_null($this->store->info['id']);
    }

    public function isLoadDataFromDB(): bool {
        return $this->store->is_load_data_from_db;
    }

    public function getModifyFields() {
        return array_keys($this->store->modify_fields);
    }

    private function addModifyFields(string $name) {
        if (isset(static::$FIELDS[$name]))
            $this->store->modify_fields[$name] = true;
    }

    /** Если сейчас режим СОЗДАНИЯ записи в БД
     * @return bool
     */
    public function isModeCreate(): bool {
        return !$this->isSetId();
    }

    /** Если сейчас режим ОБНОВЛЕНИЯ данных в БД
     * @return bool
     */
    public function isModeModify(): bool {
        return $this->isSetId();
    }

    private function setParent(self $parent) {
        $this->store->parent = $parent;
    }

    // Информация о SQL данных Table

    public function getSql(bool $is_add_original_name = false): Select {
        $fields_select = [];
        foreach (static::$FIELDS as $name => $value) {
            $field_ref = $this->getField($name)->ref();
            $fields_select[$field_ref->select_name] = $field_ref->query_name;
            if ($is_add_original_name) {
                $fields_select[$field_ref->db_name] = $field_ref->query_name;
            }
        }
        return Select::create()
            ->setSelect($fields_select)
            ->setFrom($this->getFromAlias())
            ->setLimit(1);
    }

    public function getJoin(string $type = 'INNER'): Join {
        return Join::create($type, $this->getFromAlias());
    }

    private function getFromAlias(): From {
        if (!isset($this->FROM)) {
            $this->FROM = static::getFromRaw();
            $this->FROM->setAlias(static::$SQL_ALIAS . $this->generateRandAliasPrefix());
        }

        return $this->FROM;
    }

    public function getFrom(): From {
        if (!$this->getModifyFields()) {
            return $this->getFromAlias();
        }
        $sql = $this->getSql(true);
        $sql = $this->getSqlFilter($sql);

        return From::create($sql, $this->getFromAlias()->getAlias());
    }

    public static function getFromRaw(): From {
        return From::create(static::$SQL_FROM, static::$SQL_ALIAS);
    }

    private function generateRandAliasPrefix(): string {
        return 't' . Settings::getUniqueKey();
    }

    // Инициализация поля

    private function createField($name, &$value_prototype): FLink|Field\FBool|Field\FInt|Field\FText|Field\FDouble {
        if (empty(static::$FIELDS[$name]))
            throw new Exception('Field name is invalid');

        $alias = $this->getFromAlias()->getAlias();
        $db_name = $this->getFieldRawName($name);
        $result = WithoutType::create(
            static::$FIELDS[$name]['type'],
            "{$alias}_$db_name",
            "$alias.$db_name",
            $db_name,
            $name,
            !empty(static::$FIELDS[$name]['is_required']),
            !empty(static::$FIELDS[$name]['access_modify']),
            $this
        );

        if ($result->ref()->is_table) {
            /** @var self $value_prototype */
            $value_prototype->setRef($result->ref());
        }

        return $result;
    }

    // Информация о поле

    protected function isTableField($name) {
        return static::$FIELDS[$name]['type'] === FLink::TYPE;
    }

    private function getFieldRawName($field): string {
        if (isset(static::$FIELDS[$field]['db']))
            return static::$FIELDS[$field]['db'];

        if ($this->isTableField($field))
            return $field . '_id';
        return $field;
    }

    /** Можно ли изменить данное поле в текущий момент (можно менять поле в БД или режим создания записи в БД, а не обновления)
     * @param string $name
     * @return bool
     */
    public function isAccessModifyField(string $name): bool {
        return $name !== 'id' && ($this->isModeCreate() || (isset(static::$FIELDS[$name]['access_modify']) && static::$FIELDS[$name]['access_modify']));
    }

    public function used(): static {
        if (isset($this->ref)) {
            $this->ref->parent->addModifyFields($this->ref->name);
            $this->ref->parent->used();
        }
        return $this;
    }

    // Механизмы работы с БД

    protected function initDB() {
        if (isset($this->store->parent)) {
            $this->used();
            $this->store->parent->initDB();
            unset($this->store->parent);
            return;
        }

        if ($this->store->is_load_data_from_db || !isset($this->store->info['id']) || is_null($this->store->info['id']))
            return;

        $id = $this->getField('id');
        $id_ref = $id->ref();

        $sql = $this->getSqlFilter();
        $sql->getWhere()
            ->w_and("$id_ref->query_name = $id_ref->placeholder", [$id->raw()]);

        $interfaces = [];
        $this->combineSql($sql, $interfaces, 0);

        $tmp_data = Settings::query($sql->getSql())?->fetch(\PDO::FETCH_ASSOC);

        $this->loadDataFromInterfaceInfo($this, $interfaces, $tmp_data);

        $this->parseDbData($tmp_data);
    }

    public function createItem() {
        if (!$this->isModeCreate())
            throw new Exception('Create is not access');

        $sql = Insert::create()
            ->setFrom($this->getFromAlias());

        $values = [];
        foreach (static::$FIELDS as $name => $info) {
            if ($name === 'id')
                continue;

            $value = $this->getField($name);
            $ref_value = $value->ref();

            $sql->addField($ref_value->db_name, $ref_value->placeholder);
            $raw_value = $value->raw();
            if (is_null($raw_value) && $ref_value->is_required)
                throw new Exception("$name Not access NULL");
            $values[] = $raw_value;
        }
        $sql->addValues($values);

        Settings::exec($sql->getSql());
        $this->setFieldRaw('id', Settings::getPDO()->lastInsertId());

        $this->store->is_load_data_from_db = true;

        if (static::$IS_LOAD_DATA_DB_AFTER_CREATE)
            $this->clearDbInfo();

        TableCache::saveTableCache($this);
    }

    public function removeItem() {
        $this->initDB();
        if (!$this->isModeModify())
            throw new Exception('Remove item not found');

        $id = $this->getField('id');
        $id_ref = $id->ref();

        $sql = Delete::create()
            ->setFrom($this->getFromAlias())
            ->setWhere(Where::create("$id_ref->db_name = $id_ref->placeholder", [$id->raw()]))
            ->setLimit(1);

        Settings::exec($sql->getSql());
        $this->store->is_load_data_from_db = false;
        unset($this->store->info['id']);
        unset($this->fields['id']);

        return $this;
    }

    public function updateItem() {
        if (!$this->isModeModify())
            throw new Exception('Update is not access');

        if (empty($this->store->update_fields))
            return;

        $id = $this->getField('id');
        $id_ref = $id->ref();

        $sql = Update::create()
            ->setFrom($this->getFromAlias())
            ->setWhere(Where::create("$id_ref->query_name = $id_ref->placeholder", [$id->raw()]))
            ->setLimit(1);

        foreach ($this->store->update_fields as $name => $data)
            $sql->addSet($data[0], $data[1], $data[2]);

        Settings::exec($sql->getSql());
        $this->store->update_fields = [];
    }

    public function clearDbInfo() {
        if ($this->isSetId()) {
            /** @var WithoutType $id */
            $id = $this->fields['id'];
            $id_raw = &$this->store->info['id'];
            $this->store->info = [
                'id' => &$id_raw
            ];
            $this->fields = [
                'id' => $id
            ];
        } else {
            $this->store->info = [];
            $this->fields = [];
        }
        $this->store->is_load_data_from_db = false;
        return $this;
    }

    private function combineSql(Select $sql, array &$interfaces_info, int $depth): void {

        /** @var WithoutType $field */
        foreach ($this->fields as $name => $field) {
            $field_ref = $field->ref();

            if (!$field_ref->is_table)
                continue;

            if (!empty($this->store->modify_fields[$name]) && $this->store->info[$name] instanceof self && !$this->store->info[$name]->isSetId()) {
                $interface = $this->store->info[$name];
                $interfaces_info[$name] = [
                    'value' => $interface,
                    'child' => []
                ];

                $interface->combineSql($sql, $interfaces_info[$name]['child'], $depth + 1);
                continue;
            }

            if ($this->getMaxDepth() <= $depth)
                continue;

            if (!empty($this->store->info[$name]))
                $interface = $this->store->info[$name];
            else
                $interface = call_user_func([static::$FIELDS[$name]['table'], 'create']);

            if ($interface instanceof self) {
                $this->store->info[$name] = $interface;
                $interfaces_info[$name] = [
                    'value' => $interface,
                    'child' => []
                ];

                $interface_sql = $interface->getSql();

                $join = $interface->getJoin(empty(static::$FIELDS[$name]['is_required']) ? 'LEFT' : 'INNER');
                $join->setWhere($interface_sql->getWhere());
                $join->getWhere()->w_and($field_ref->query_name . " = " . $interface->getField('id')->ref()->query_name);

                $sql->addJoin($join);
                $sql->addSelectList($interface_sql->getSelect());

                $interface->combineSql($sql, $interfaces_info[$name]['child'], $depth + 1);
            }
        }
    }

    /**
     * @param Table $interface Интерфейс текущий (обрабатываемый)
     * @param array $interfaces_fields Поля интерфейса
     * @param $tmp_info
     * @param $de
     * @return void
     * @throws Exception
     */
    private function loadDataFromInterfaceInfo(self $interface, array $interfaces_fields, $tmp_info, $de = '') {
        $interface->fields = [];
        /** @var array{value: Table, children: array} $info */
        foreach ($interfaces_fields as $name => $info) {
            $field_ref = $info['value']->ref();
            if (empty($tmp_info[$field_ref->select_name]))
                throw new Exception('DBInfo not found');
            /** @var Table $item */
            $item = call_user_func([static::$FIELDS[$name]['table'], 'create'], $tmp_info[$field_ref->select_name]);

            if (isset($interface->store->info[$name])) {
                $item->setRef($interface->store->info[$name]->ref);
                $interface->store->info[$name]->store = $item->store;
            }
            $item->FROM = $info['value']->FROM;

            $interface->store->info[$name] = $item;
            $interface->fields[$name] = $interface->createField($name, $interface->store->info[$name]);
            $interface->store->info[$name]->loadDataFromInterfaceInfo($interface->store->info[$name], $info['child'], $tmp_info, $de . ' === ');
        }
        $interface->parseDbData($tmp_info);
    }

    // Парсинг данных из БД

    protected function parseDbData(array|null|bool $tmp_info = null) {
        if (isset($this->store->parent)) {
            unset($this->store->parent);
        }

        if ($this->store->is_load_data_from_db)
            return;

        if (!$tmp_info)
            throw new Exception('DBInfo not found');

        foreach (static::$FIELDS as $name => $info) {
            $field = $this->getField($name);
            $field_ref = $field->ref();

            if ($field_ref->is_table) {
                if ($field->isSetId())
                    continue;

                $this->store->info[$name] = call_user_func(
                    [static::$FIELDS[$name]['table'], 'create'], $tmp_info[$field_ref->select_name]
                );
                $this->fields[$name] = $this->createField($name, $this->store->info[$name]);
            } else {
                $this->store->info[$name] = $tmp_info[$field_ref->select_name];
            }
        }

        $this->store->is_load_data_from_db = true;

        TableCache::saveTableCache($this);
    }

    // Работа с фильтрами

    protected function setCustomFilter(Where $where = null): self {
        $this->store->custom_filter_condition = is_null($where) ? Where::create() : $where;
        $this->used();

        return $this;
    }

    protected function addCustomFilter(): Where {
        if (empty($this->store->custom_filter_condition))
            $this->store->custom_filter_condition = Where::create();
        else
            $this->store->custom_filter_condition = Where::create($this->getCustomFilter());
        $this->used();

        return $this->store->custom_filter_condition;
    }

    protected function getCustomFilter(): Where {
        return $this->store->custom_filter_condition;
    }

    protected function isSetCustomFilter(): bool {
        return isset($this->store->custom_filter_condition);
    }

    protected function setCustomFilterOrderBy(array $order_by = []): self {
        $this->store->custom_filter_order_by = $order_by;
        $this->used();

        return $this;
    }

    protected function addCustomFilterOrderBy(array $order_by): self {
        if (!isset($this->store->custom_filter_order_by))
            $this->store->custom_filter_order_by = [];
        $this->store->custom_filter_order_by += $order_by;
        $this->used();

        return $this;
    }

    protected function getCustomFilterOrderBy(): array {
        if (!isset($this->store->custom_filter_order_by))
            $this->store->custom_filter_order_by = [];
        return $this->store->custom_filter_order_by;
    }

    protected function isSetCustomFilterOrderBy(): bool {
        return isset($this->store->custom_filter_order_by);
    }

    private function getSqlFilter(Select $sql = null): Select {
        if (is_null($sql))
            $sql = $this->getSql();

        if ($this->isSetCustomFilterOrderBy())
            $sql->setOrderBy($this->getCustomFilterOrderBy());

        foreach ($this->getModifyFields() as $name) {

            $field = $this->getField($name);
            $field_ref = $field->ref();

            if ($field_ref->is_table && $this->store->info[$name] instanceof self && !$this->store->info[$name]->isSetId()) {
                /** @var Select $interface_sql */
                $interface_sql = $field->getSqlFilter(null);

                $sql->addSelectList($interface_sql->getSelect());

                if (!$interface_sql->getWhere()->isEmpty()) {
                    /** @var Join $join */
                    $join = $field->getJoin('INNER');
                    $join->setWhere($interface_sql->getWhere());
                } else {
                    /** @var Join $join */
                    $join = $field->getJoin($field_ref->is_required ? 'INNER' : 'LEFT');
                }
                if (!empty($interface_sql->getOrderBy()))
                    $sql->addOrderBy($interface_sql->getOrderBy());

                $join->getWhere()->w_and("$field_ref->query_name = " . $field->id->ref()->query_name);

                $sql->addJoin($join);
                $sql->addJoin($interface_sql->getJoinList());
                continue;
            }

            $sql->getWhere()->w_and("$field_ref->query_name = $field_ref->placeholder", [$field->raw()]);
        }

        if ($this->isSetCustomFilter())
            $sql->getWhere()->w_and($this->getCustomFilter());

        return $sql;
    }

    // Получить наборы данных с фильтром

    public function find(): ?static {
        $sql = $this->getSqlFilter();
        $sql->setLimit(1);

        $interfaces_info = [];
        $this->combineSql($sql, $interfaces_info, 0);

        $tmp_info = Settings::query($sql->setLimit()->getSql())?->fetch(\PDO::FETCH_ASSOC);

        if (!$tmp_info)
            return null;

        $item = static::create($tmp_info[$this->getField('id')->ref()->select_name]);
        $item->FROM = $this->FROM;

        $this->loadDataFromInterfaceInfo($item, $interfaces_info, $tmp_info);

        return $item;
    }

    public function findAll(): SmartList {
        $result = new SmartList(static::class);

        foreach ($this->findAllGen() as $item) {
            $result[] = $item;
        }

        return $result;
    }

    public function findAllGen(): \Generator {
        $sql = $this->getSqlFilter();

        $sql->setLimit();

        $interfaces_info = [];
        $this->combineSql($sql, $interfaces_info, 0);

        $tmp_info_q = Settings::query($sql->setLimit()->getSql());
        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = static::create($tmp_info[$this->getField('id')->ref()->select_name]);
            $item->FROM = $this->FROM;
            $this->loadDataFromInterfaceInfo($item, $interfaces_info, $tmp_info);

            yield $item;
        }
    }

    // Валидация кастомная

    private function fieldValidateCustom($name, $value): bool {
        if (empty(static::$FIELDS[$name]['validate']))
            return true;

        $raw_value = $value instanceof self ? $value->getId() : $value;

        if (isset(static::$FIELDS[$name]['validate']['equal']) && static::$FIELDS[$name]['validate']['equal'] !== $raw_value)
            return false;

        if (isset(static::$FIELDS[$name]['validate']['not_equal']) && static::$FIELDS[$name]['validate']['not_equal'] === $raw_value)
            return false;

        if (isset(static::$FIELDS[$name]['validate']['in']) && !in_array($raw_value, static::$FIELDS[$name]['validate']['in'], true))
            return false;

        if (isset(static::$FIELDS[$name]['validate']['not_in']) && in_array($raw_value, static::$FIELDS[$name]['validate']['not_in'], true))
            return false;

        if (isset(static::$FIELDS[$name]['validate']['compare'])) {
            if (static::$FIELDS[$name]['validate']['compare'][0] === '>' && $raw_value <= static::$FIELDS[$name]['validate']['compare'][1])
                return false;
            if (static::$FIELDS[$name]['validate']['compare'][0] === '<' && $raw_value >= static::$FIELDS[$name]['validate']['compare'][1])
                return false;
            if (static::$FIELDS[$name]['validate']['compare'][0] === '>=' && $raw_value < static::$FIELDS[$name]['validate']['compare'][1])
                return false;
            if (static::$FIELDS[$name]['validate']['compare'][0] === '<=' && $raw_value > static::$FIELDS[$name]['validate']['compare'][1])
                return false;
        }

        if (isset(static::$FIELDS[$name]['validate']['preg']) && !preg_match(static::$FIELDS[$name]['validate']['preg'], $raw_value))
            return false;

        if (isset(static::$FIELDS[$name]['validate']['func'])) {
            $func = static::$FIELDS[$name]['validate']['func'];
            if ($func[0] === '__this')
                $func[0] = $this;
            if (!$func($value, $name))
                return false;
        }

        return true;
    }
}
