<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\InterfaceDB\Field\FInt;
use DigitalStars\InterfaceDB\Field\FLink;
use DigitalStars\InterfaceDB\Field\FReflection;
use DigitalStars\InterfaceDB\Field\WithoutType;
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

    protected static array $FIELDS = [
        'id' => [
            'type' => 'int'
        ],
        'user_id' => [
            'type' => 'int',
            'part_id' => true
        ]
    ];

    private array $info;
    private Select $sql;
    private ?string $id;

    // Магические свойства и обработка значений по умолчанию
    private function __construct(Select $sql) {
        $this->sql = $sql;
    }

    public function __get($name) {
        return $this->getField($name);
    }

    public function __set($name, $value) {
        return $this->setField($name, $value);
    }

    public function __isset($name) {
        return isset(static::$FIELDS[$name]);
    }

    public function getId(): mixed {
        if (!empty($this->id)) {
            return $this->id;
        }

        $this->id = $this->createIdKey();

        return $this->id;
    }

    private function createIdKey(bool $is_ignore_flag = false): string {
        $id_key = [];

        $field_list = static::$FIELDS;
        if ($is_ignore_flag) {
            $group_fields = $this->sql->getGroupBy();
            if ($group_fields)
                $field_list = array_fill_keys($group_fields, true);
        }

        foreach ($field_list as $field => $info) {
            if (!$is_ignore_flag && empty($info['part_id']))
                continue;

            $id_key[] = (string)($this->isTableField($field) ? $this->info[$field]->getId() : $this->info[$field]);
        }

        if (empty($id_key)) {
            if (!$is_ignore_flag)
                return $this->createIdKey(true);
            else
                throw new Exception('Id is invalid');
        }
        return implode('_', $id_key);
    }

    // Геттеры

    public function getField(string $name): mixed {
        if ($name === 'id')
            return $this->getId();
        return $this->info[$name] ?? null;
    }

    public function raw(): ?int {
        return $this->getId();
    }

    public function getSql(): Select {
        return $this->sql;
    }

    // Сеттеры

    public function setField($name, $value): ?static {
        throw new Exception('Set value is not support');
    }

    // Конструктор

    protected static function create(Select $sql): ?static {
        return self::createGenerator($sql->setLimit(1))->current();
    }

    protected static function createList(Select $sql): SmartList {
        $result = new SmartList(static::class);

        foreach (self::createGenerator($sql) as $item)
            $result[] = $item;

        return $result;
    }

    protected static function createGenerator(Select $sql): \Generator {
        $tmp_info_q = Main::query($sql->getSql());

        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = new static($sql);
            $item->parseDbData($tmp_info);
            yield $item;
        }
    }

    // Информация о поле

    protected function isTableField($name): bool {
        return is_subclass_of(static::$FIELDS[$name]['type'], Table::class);
    }

    // Парсинг БД

    private function parseDbData(array|null|bool $tmp_info = null) {
        if (!$tmp_info)
            return null;

        foreach (static::$FIELDS as $name => $info) {
            if ($this->isTableField($name)) {
                $this->info[$name] = call_user_func(
                    [static::$FIELDS[$name]['type'], 'create'], $tmp_info[$name]
                );
            } else {
                $this->info[$name] = $this->validateTypeField($name, $tmp_info[$name]);
            }
        }
    }

    private function validateTypeField(string $name, $value) {
        if (is_null($value))
            return null;

        switch (static::$FIELDS[$name]['type']) {
            case 'bool':
                return (bool)$value;
            case 'double':
                return (double)$value;
            case 'int':
                return (int)$value;
            case 'string':
                return (string)$value;
            default:
                return $value;
        }
    }
}
