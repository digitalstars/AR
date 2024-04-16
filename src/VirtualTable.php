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
abstract class VirtualTable implements SmartListItem {
    use Tools;

    protected static array $FIELDS = [
        'id' => [
            'type' => 'int'
        ],
        'user_id' => [
            'type' => 'int',
            'is_part_id' => true
        ]
    ];

    // Магические свойства и обработка значений по умолчанию
    protected function __construct() {

    }

    public function __get($name) {
        $value = $this->getUserField($name);

        return $this->fieldValidateType($name, $value);
    }

    public function __set($name, $value) {
        throw new Exception('Set value is not support');
    }

    public function __isset($name) {
        return isset(static::$FIELDS[$name]);
    }

    protected static function create() {
        return new static();
    }

    public function isValid(): bool {
        try {
            $this->initDbInfo();
        } catch (Exception $e) {
            if ($e->getMessage() === 'DBInfo not found')
                return false;
            throw $e;
        }
        return true;
    }

    public function isLoadDataFromDB(): bool {
        return $this->is_load_data_from_db;
    }

    public function getId(): ?int {
        $this->initDbInfo();

        if (!empty($this->id)) {
            return $this->id;
        }

        $this->id = $this->createIdKey();

        return null;
    }

    protected function getUserField($name) {
        $this->initDbInfo();

        if ($name === 'id')
            return $this->id;

        return $this->info[$name] ?? null;
    }

    private Select $sql;

    public function getSql(): Select {
        return $this->sql;
    }

    protected function setSql(Select $sql) {
        if ($this->is_load_data_from_db)
            throw new Exception('DBInfo is load');

        $this->sql = $sql;
    }

    protected function initDbInfo() {
        if ($this->isLoadDataFromDB())
            return;

        $sql = $this->getSql()->setLimit(1);

        $tmp_data = Main::query($sql->getSql())?->fetch();

        $this->parseDbData($tmp_data);
    }

    protected function parseDbData(array|null|bool $tmp_info = null) {
        if ($this->isLoadDataFromDB())
            return;

        if (!$tmp_info)
            throw new Exception('DBInfo not found');

        foreach ($this->getSelectFields() as $name => $info) {
            $is_object = $this->fieldIsObject($name);
            if ($is_object) {
                if (empty($this->info[$name]))
                    $this->info[$name] = call_user_func(
                        [static::$FIELDS[$name]['type'], 'create'], $tmp_info[$info['query_name']]
                    );
            } else {
                $this->info[$name] = $tmp_info[$info['query_name']];
            }
        }

        $this->is_load_data_from_db = true;
    }

    private function createIdKey(): string {
        $id_key = [];
        foreach (static::$FIELDS as $field => $info) {
            if (empty($info['is_part_id']))
                continue;

            $id_key[] = (string)($this->fieldIsObject($field) ? $this->info[$field]->getId() : $this->info[$field]);
        }
        return empty($id_key) ? throw new Exception('Id is invalid') : implode('_', $id_key);
    }

    protected function getListFromSQL(Select $sql): SmartList {
        $result = new SmartList(static::class);

        $tmp_info_q = Main::query($sql->setLimit()->getSql());

        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = new static();
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

        $item = new static();
        $item->parseDbData($tmp_info);

        return $item;
    }

    private function getSelectFields(): array {
        if (!empty($this->cache_select_fields))
            return $this->cache_select_fields;

        foreach (static::$FIELDS as $name => $info) {
            $is_object = !in_array($info['type'], ['bool', 'int', 'string', 'double'], true);
            $this->cache_select_fields[$name] = [
                'is_object' => $is_object
            ];
        }

        return $this->cache_select_fields;
    }

    protected function fieldIsObject($name) {
        return $this->getSelectFields()[$name]['is_object'];
    }

    private array $info = [];
    private ?string $id;
    private bool $is_load_data_from_db = false;
    private array $cache_select_fields;
}
