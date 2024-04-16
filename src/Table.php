<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\InterfaceDB\SmartList\SmartList;
use DigitalStars\InterfaceDB\SmartList\SmartListItem;
use DigitalStars\SimpleSQL\Components\From;
use DigitalStars\SimpleSQL\Components\Join;
use DigitalStars\SimpleSQL\Components\Where;
use DigitalStars\SimpleSQL\Delete;
use DigitalStars\SimpleSQL\Insert;
use DigitalStars\SimpleSQL\Select;
use DigitalStars\SimpleSQL\Update;

/**
 * @property-read  int id
 */
abstract class Table implements SmartListItem {
    use Tools;

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
            'type' => 'int',
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

    protected int|null $MAX_DEPTH = null;
    protected bool|null $IS_LAZY_UPDATE = null;

    protected static bool $IS_LOAD_DATA_DB_AFTER_CREATE = false; // Вытащить из БД данные после создания записи

    // Магические свойства и обработка значений по умолчанию
    protected function __construct(?int $id = null) {
        $this->base = new Base();
        $this->base->id = $id;
        if ($id)
            static::saveSelfCache($this);
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
        return $this->base->id;
    }

    public function isSetId(): bool {
        return !is_null($this->base->id);
    }

    public function clearDbInfo() {
        if (!$this->base->is_load_data_from_db)
            return $this;

        $this->base->info = [];
        $this->base->is_load_data_from_db = false;
        return $this;
    }

    public function isLoadDataFromDB(): bool {
        return $this->base->is_load_data_from_db;
    }

    public function getModifyFields() {
        return array_keys($this->base->modify_fields);
    }

    public function runUpdate() {
        if (!$this->isModeModify())
            throw new Exception('Update is not access');

        if (empty($this->base->update_fields))
            return;

        $sql = Update::create()
            ->setFrom($this->getFrom())
            ->setWhere(Where::create('id = ?i', [$this->base->id]))
            ->setLimit(1);

        foreach ($this->base->update_fields as $name => $data)
            $sql->addSet($data[0], $data[1], $data[2]);

        Main::exec($sql->getSql());
        $this->base->update_fields = [];
    }

    public function createItem() {
        if (!$this->isModeCreate())
            throw new Exception('Create is not access');

        $sql = Insert::create()
            ->setFrom($this->getFrom());

        $values = [];
        foreach (static::$FIELDS as $name => $info) {
            if ($name === 'id')
                continue;

            $sql->addField($this->getFieldRawName($name), $this->getFieldPlaceholder($name));
            $value = $this->getRawValueFromDB($this->$name);
            if (is_null($value) && $info['is_required'])
                throw new Exception("$name Not access NULL");
            $values[] = $value;
        }
        $sql->addValues($values);

        Main::exec($sql->getSql());
        $this->base->id = Main::getPDO()->lastInsertId();

        $this->base->is_load_data_from_db = true;

        if (static::$IS_LOAD_DATA_DB_AFTER_CREATE)
            $this->clearDbInfo();

        self::saveSelfCache($this);
    }

    public function removeItem() {
        $this->initDbInfo();
        if (!$this->isModeModify())
            throw new Exception('Remove item not found');

        $sql = Delete::create()
            ->setFrom($this->getFrom())
            ->setWhere(Where::create($this->getFieldRawName('id') . ' = ?i', [$this->base->id]))
            ->setLimit(1);

        Main::exec($sql->getSql());
        $this->base->id = null;
        $this->base->is_load_data_from_db = false;

        return $this;
    }

    public static function create(?int $id = null) {
        if ($id)
            return self::getSelfCacheForId($id) ?: new static($id);
        return new static($id);
    }

    public function getSql(): Select {
        return Select::create()
            ->setSelect(
                array_combine(
                    array_column($this->getSelectFields(), 'query_name'),
                    array_column($this->getSelectFields(), 'db_name')))
            ->setFrom($this->getFrom())
            ->setLimit(1);
    }

    public function getJoin(string $type = 'INNER'): Join {
        return Join::create($type, $this->getFrom());
    }

    public function find(): ?static {
        $sql = $this->getSqlFilter();
        $sql->setLimit(1);

        $interfaces_info = [];
        $this->combineSql($sql, $interfaces_info, 0);

        $tmp_info = Main::query($sql->setLimit()->getSql())?->fetch();

        if (!$tmp_info)
            return null;

        $item = static::create($tmp_info[$this->getFieldAliasName('id')]);
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

        $tmp_info_q = Main::query($sql->setLimit()->getSql());
        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = static::create($tmp_info[$this->getFieldAliasName('id')]);
            $item->FROM = $this->FROM;
            $this->loadDataFromInterfaceInfo($item, $interfaces_info, $tmp_info);

            yield $item;
        }
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

    /** Можно ли изменить данное поле в текущий момент (можно менять поле в БД или режим создания записи в БД, а не обновления)
     * @param string $name
     * @return bool
     */
    public function isAccessModifyField(string $name): bool {
        return $this->isModeCreate() || (isset(static::$FIELDS[$name]['access_modify']) && static::$FIELDS[$name]['access_modify']);
    }

    public function used(): static {
        return $this;
    }

    public function getMaxDepth(): int {
        return is_null($this->MAX_DEPTH) ? Main::getMaxDepth() : $this->MAX_DEPTH;
    }

    public function setMaxDepth(?int $max_depth = null): static {
        $this->MAX_DEPTH = $max_depth;
        return $this;
    }

    public function getIsLazyUpdate(): bool {
        return is_null($this->IS_LAZY_UPDATE) ? Main::getIsLazyUpdate() : $this->IS_LAZY_UPDATE;
    }

    public function setIsLazyUpdate(?int $is_lazy_update = null): static {
        $this->IS_LAZY_UPDATE = $is_lazy_update;
        return $this;
    }

    public static function getFromRaw(): From {
        return From::create(static::$SQL_FROM, static::$SQL_ALIAS);
    }

    public function ref(): TableReflection {
        static $table_reflection = null;
        if (!$table_reflection)
            $table_reflection = TableReflection::create($this->getFrom(), $this->getFieldRawName('id'));

        return $table_reflection;
    }

    public static function refRaw(): TableReflection {
        static $table_reflection = null;
        if (!$table_reflection)
            $table_reflection = TableReflection::create(static::getFromRaw(), static::$FIELDS['id']['db'] ?? 'id');

        return $table_reflection;
    }

    protected function getUserField($name) {
        if ($this->fieldIsObject($name)) {
            $result = $this->base->info[$name] ?? null;
            if (is_null($result)) {
                /** @var self $result */
                $result = call_user_func([static::$FIELDS[$name]['type'], 'create']);

                if ($this->isSetId() || isset($this->base->parent)) {
                    $this->base->info[$name] = $result;
                    $result->setParent($this);
                } else {
                    $this->setUserField($name, $result);
                }
            }

            return $result;
        }

        if ($name === 'id')
            return $this->base->id;

        $this->initDbInfo();

        return $this->base->info[$name] ?? null;
    }

    protected function setUserField($name, $value) {
        if (!$this->isAccessModifyField($name))
            throw new Exception("$name Is not access modify");

        if ($name === 'id') {
            throw new Exception('ID is not modify');
        }

        if (!$this->fieldValidateCustom($name, $value))
            throw new Exception('Value invalid');

        $this->initDbInfo();

        $old_value = $this->base->info[$name] ?? null;

        if ($this->isSetCustomFilter() && $value instanceof self && $old_value instanceof self) {
            $value->FROM = $old_value->FROM;
        }

        $this->base->info[$name] = $value;

        if ($this->isModeModify())
            $this->updateDB($name, $value, $old_value);
        $this->base->modify_fields[$name] = true;
    }

    protected function initDbInfo() {
        if (isset($this->base->parent)) {
            $this->base->parent->initDbInfo();
            unset($this->base->parent);
            return;
        }

        if ($this->base->is_load_data_from_db || is_null($this->base->id))
            return;

        $sql = $this->getSqlFilter();
        $sql->getWhere()->w_and($this->getFieldQueryName('id') . ' = ?i', [$this->base->id]);

        $interfaces = [];
        $this->combineSql($sql, $interfaces, 0);

        $tmp_data = Main::query($sql->getSql())?->fetch();

        $this->loadDataFromInterfaceInfo($this, $interfaces, $tmp_data);

        $this->parseDbData($tmp_data);
    }

    protected function parseDbData(array|null|bool $tmp_info = null) {
        if ($this->base->is_load_data_from_db)
            return;

        if (!$tmp_info)
            throw new Exception('DBInfo not found');

        foreach ($this->getSelectFields() as $name => $info) {
            $is_object = $this->fieldIsObject($name);
            if ($is_object) {
                if (empty($this->base->info[$name]))
                    $this->base->info[$name] = call_user_func(
                        [static::$FIELDS[$name]['type'], 'create'], $tmp_info[$info['query_name']]
                    );
            } else {
                $this->base->info[$name] = $tmp_info[$info['query_name']];
            }
        }

        if (is_null($this->base->id)) {
            if (empty($this->base->info['id']))
                throw new Exception('DBInfo not found');

            $this->base->id = $this->base->info['id'];
        }

        $this->base->is_load_data_from_db = true;

        static::saveSelfCache($this);
    }

    protected function updateDB(string $name, $value, $old_value) {
        $this->base->update_fields[$name] = [
            $this->getFieldRawName($name),
            $this->getFieldPlaceholder($name),
            $this->getRawValueFromDB($value)
        ];
        if (!$this->getIsLazyUpdate())
            $this->runUpdate();
    }

    protected function getRawValueFromDB($value) {
        if (is_null($value))
            return null;
        if (is_object($value)) {
            if ($value instanceof self)
                return $value->getId();
            else
                throw new Exception('Update type not found');
        } else
            return $value;
    }

    protected function getFieldRawName($field, $is_object = null): string {
        if (isset(static::$FIELDS[$field]['db']))
            return static::$FIELDS[$field]['db'];

        if (is_null($is_object))
            $is_object = $this->fieldIsObject($field);

        if ($is_object)
            return $field . '_id';
        return $field;
    }

    protected function getFieldQueryName($field): string {
        return $this->getSelectFields()[$field]['db_name'];
    }

    protected function getFieldAliasName($field): string {
        return $this->getSelectFields()[$field]['query_name'];
    }

    protected function getFieldPlaceholder($field): string {
        return $this->getSelectFields()[$field]['placeholder'];
    }

    protected function getListFromSQL(Select $sql): SmartList {
        $result = new SmartList(static::class);

        $tmp_info_q = Main::query($sql->setLimit()->getSql());

        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = static::create($tmp_info[$this->getFieldAliasName('id')]);
            $item->FROM = $this->FROM;

            if (!$item->isLoadDataFromDB())
                $item->parseDbData($tmp_info);

            $result[] = $item;
        }

        return $result;
    }

    protected function createFromSql(Select $sql): ?static {
        $sql->setLimit(1);

        $tmp_info = Main::query($sql->setLimit()->getSql())?->fetch();

        if (!$tmp_info)
            return null;

        $item = static::create($tmp_info[$this->getFieldAliasName('id')]);
        $item->FROM = $this->FROM;

        if (!$item->isLoadDataFromDB())
            $item->parseDbData($tmp_info);

        return $item;
    }

    protected static string $SQL_FROM = '';
    protected static string $SQL_ALIAS = '';

    protected From $FROM;

    protected function getFrom(): From {
        if (!isset($this->FROM)) {
            $this->FROM = static::getFromRaw();
            $this->FROM->setAlias(static::$SQL_ALIAS . $this->generateRandAliasPrefix());
        }

        return $this->FROM;
    }

    protected function fieldIsObject($name) {
        return $this->getSelectFields()[$name]['is_object'];
    }

    protected function setCustomFilter(Where $where): self {
        $this->base->custom_filter_condition = $where;
        return $this;
    }

    protected function addCustomFilter(): Where {
        if (empty($this->base->custom_filter_condition))
            $this->base->custom_filter_condition = Where::create();
        else
            $this->base->custom_filter_condition = Where::create($this->getCustomFilter());

        return $this->base->custom_filter_condition;
    }

    protected function getCustomFilter(): Where {
        return $this->base->custom_filter_condition;
    }

    protected function isSetCustomFilter(): bool {
        return isset($this->base->custom_filter_condition);
    }

    protected function setCustomFilterOrderBy(array $order_by = []): self {
        $this->base->custom_filter_order_by = $order_by;
        return $this;
    }

    protected function addCustomFilterOrderBy(array $order_by): self {
        if (!isset($this->base->custom_filter_order_by))
            $this->base->custom_filter_order_by = [];
        $this->base->custom_filter_order_by += $order_by;
        return $this;
    }

    protected function getCustomFilterOrderBy(): array {
        if (!isset($this->base->custom_filter_order_by))
            $this->base->custom_filter_order_by = [];
        return $this->base->custom_filter_order_by;
    }

    protected function isSetCustomFilterOrderBy(): bool {
        return isset($this->base->custom_filter_order_by);
    }

    public Base $base;

    private function setParent(self $parent) {
        $this->base->parent = $parent;
    }

    private function getSelectFields(): array {
        if (!empty($this->base->cache_select_fields))
            return $this->base->cache_select_fields;

        $alias = $this->getFrom()->getAlias();
        foreach (static::$FIELDS as $name => $info) {
            $is_object = !in_array($info['type'], ['bool', 'int', 'string', 'double'], true);
            $db_name = $this->getFieldRawName($name, $is_object);
            $this->base->cache_select_fields[$name] = [
                'name' => $name,
                'db_name' => "$alias.$db_name",
                'query_name' => "{$alias}_$db_name",
                'is_object' => $is_object,
                'placeholder' => $this->TypeToPlaceholder($is_object ? 'int' : $info['type'])
            ];
        }

        return $this->base->cache_select_fields;
    }

    private function generateRandAliasPrefix(): string {
        return 't' . rand(1000, 9999);
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

    private function combineSql(Select $sql, array &$interfaces_info, int $depth): void {

        foreach ($this->getSelectFields() as $name => $info) {
            if (!$info['is_object'])
                continue;

            if (!empty($this->base->info[$name]) && $this->base->info[$name] instanceof self && !$this->base->info[$name]->isSetId()) {
                $interface = $this->base->info[$name];
                $interfaces_info[$name] = [
                    'value' => $interface,
                    'child' => []
                ];

                $interface->combineSql($sql, $interfaces_info[$name]['child'], $depth + 1);
                continue;
            }

            if ($this->getMaxDepth() <= $depth)
                continue;

            if (!empty($this->base->info[$name]))
                $interface = $this->base->info[$name];
            else
                $interface = call_user_func([static::$FIELDS[$name]['type'], 'create']);

            if ($interface instanceof self) {
                $this->base->info[$name] = $interface;
                $interfaces_info[$name] = [
                    'value' => $interface,
                    'child' => []
                ];

                $interface_sql = $interface->getSql();

                $join = $interface->getJoin(empty(static::$FIELDS[$name]['is_required']) ? 'LEFT' : 'INNER');
                $join->setWhere($interface_sql->getWhere());
                $join->getWhere()->w_and($this->getFieldQueryName($name) . ' = ' . $interface->getFieldQueryName('id'));

                $sql->addJoin($join);
                $sql->addSelectList($interface_sql->getSelect());

                $interface->combineSql($sql, $interfaces_info[$name]['child'], $depth + 1);
            }
        }
    }

    private function loadDataFromInterfaceInfo(self $interface, array $interfaces_info, $tmp_info, $de = '') {
        foreach ($interfaces_info as $name => $info) {
            $item = call_user_func([static::$FIELDS[$name]['type'], 'create'], $tmp_info[$info['value']->getFieldAliasName('id')]);
            if (isset($interface->base->info[$name])) {
                $interface->base->info[$name]->base = $item->base;
            }
            $info['value']->base = $item->base;
            $interface->base->info[$name] = $item;
            $interface->base->info[$name]->FROM = $info['value']->FROM;
            $interface->base->info[$name]->loadDataFromInterfaceInfo($interface->base->info[$name], $info['child'], $tmp_info, $de . ' === ');
        }
        $interface->parseDbData($tmp_info);
    }

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

    private function fieldValidateCustom($name, $value): bool {
        if (empty(static::$FIELDS[$name]['validate']))
            return true;

        $raw_value = $this->getRawValueFromDB($value);

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

    private function getSqlFilter(Select $sql = null): Select {
        if (is_null($sql))
            $sql = $this->getSql();

        if ($this->isSetCustomFilterOrderBy())
            $sql->setOrderBy($this->getCustomFilterOrderBy());

        foreach (static::$FIELDS as $field => $info) {
            if (empty($this->base->modify_fields[$field]) && !(!empty($this->base->info[$field]) && $this->base->info[$field] instanceof self && !$this->base->info[$field]->isSetId()))
                continue;


            $is_object = $this->fieldIsObject($field);

            /** @var self $item */
            $item = $this->base->info[$field];

            if ($is_object && $this->base->info[$field] instanceof self && !$this->base->info[$field]->isSetId()) {
                /** @var Select $interface_sql */
                $interface_sql = $item->getSqlFilter(null);

                $sql->addSelectList($interface_sql->getSelect());

                if (!$interface_sql->getWhere()->isEmpty()) {
                    /** @var Join $join */
                    $join = $item->getJoin('INNER');
                    $join->setWhere($interface_sql->getWhere());
                } else {
                    /** @var Join $join */
                    $join = $item->getJoin(empty($info['is_required']) ? 'LEFT' : 'INNER');
                }
                if (!empty($interface_sql->getOrderBy()))
                    $sql->addOrderBy($interface_sql->getOrderBy());

                $join->getWhere()->w_and($this->getFieldQueryName($field) . ' = ' . $item->getFieldQueryName('id'));

                $sql->addJoin($join);
                $sql->addJoin($interface_sql->getJoinList());
                continue;
            }

            $placeholder = $this->getFieldPlaceholder($field);
            $raw_value = $this->getRawValueFromDB($item);
            $sql->getWhere()->w_and($this->getFieldQueryName($field) . " = $placeholder", [$raw_value]);
        }

        if ($this->isSetCustomFilter())
            $sql->getWhere()->w_and($this->getCustomFilter());

        return $sql;
    }

    // Кеширование

    private static array $SUPER_CACHE_TABLES = [];

    private static function saveSelfCache(self $item) {
        if ($item->isSetId()) {
            if (!isset(self::$SUPER_CACHE_TABLES[static::class][$item->base->id])) {
                self::$SUPER_CACHE_TABLES[static::class][$item->base->id] = $item;
            } else if (self::$SUPER_CACHE_TABLES[static::class][$item->base->id] !== $item) {
                throw new Exception('Init clone class!!');
            }
        }
    }

    private static function getSelfCacheForId(int $id): self|null {
        if (isset(self::$SUPER_CACHE_TABLES[static::class][$id]))
            return self::$SUPER_CACHE_TABLES[static::class][$id];
        return null;
    }

    private static function getSelfCacheForClassnameAndId(string $class_name, int $id): self|null {
        if (isset(self::$SUPER_CACHE_TABLES[$class_name][$id]))
            return self::$SUPER_CACHE_TABLES[$class_name][$id];
        return null;
    }

    public static function print_r_super_cache() {
        echo "\n";
        foreach (self::$SUPER_CACHE_TABLES as $class_name => $object_list) {
            /** @var self $object */
            foreach ($object_list as $id => $object) {
                echo "$class_name -> $id. Is_init: " . ($object->isLoadDataFromDB() ? 1 : 0) . "\n";
            }
        }
        echo "\n";
    }
}
