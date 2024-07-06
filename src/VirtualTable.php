<?php

namespace DigitalStars\AR;

use JsonSerializable;
use DigitalStars\AR\Field\FInt;
use DigitalStars\AR\Field\FLink;
use DigitalStars\AR\Field\FText;
use DigitalStars\AR\Field\WithoutType;
use DigitalStars\AR\Helpers\TableBase;
use DigitalStars\AR\SmartList\SmartList;
use DigitalStars\AR\SmartList\SmartListItem;
use DigitalStars\SimpleSQL\Select;

/**
 * @property-read  int id
 */
abstract class VirtualTable implements SmartListItem, JsonSerializable {
    use TableBase;

    protected static array $FIELDS = [
        'id' => [
            'type' => FText::TYPE
        ],
        'user_id' => [
            'type' => FInt::TYPE,
            'part_id' => true
        ]
    ];

    private array $info;
    private array $fields;
    private Select $sql;
    private ?string $id;

    // Магические свойства и обработка значений по умолчанию
    private function __construct(Select $sql) {
        $this->sql = $sql;
    }

    public function __isset($name) {
        return isset(static::$FIELDS[$name]) || $name === 'id';
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

    private function createEmptyField(string $name) {
        $this->fields[$name] = $this->createField($name);
    }

    public function getFieldValue(string $name) {
        if ($name === 'id')
            return $this->getId();
        if (empty(static::$FIELDS[$name]))
            throw new Exception("Field $name is not found in: " . static::class);

        return $this->info[$name];
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
        $tmp_info_q = Settings::query($sql->getSql());

        while ($tmp_info = $tmp_info_q->fetch()) {
            $item = new static($sql);
            $item->parseDbData($tmp_info);
            yield $item;
        }
    }

    // Информация о поле

    protected function isTableField($name): bool {
        return $name !== 'id' && static::$FIELDS[$name]['type'] === FLink::TYPE;
    }

    private function createField($name): FLink|Field\FBool|Field\FInt|Field\FText|Field\FDouble {
        if (empty(static::$FIELDS[$name]) && $name !== 'id')
            throw new Exception('Field name is invalid');

        $field_info = $name !== 'id'
            ? static::$FIELDS[$name]
            : [
                'type' => FText::TYPE,
                'is_required' => true,
                'access_modify' => false
            ];

        $result = WithoutType::create(
            $field_info['type'],
            $name,
            $name,
            $name,
            $name,
            !empty($field_info['is_required']),
            !empty($field_info['access_modify']),
            $this
        );

        return $result;
    }

    // Парсинг БД

    private function parseDbData(array|null|bool $tmp_info = null) {
        if (!$tmp_info)
            return null;

        foreach (static::$FIELDS as $name => $info) {
            if ($this->isTableField($name)) {
                $this->info[$name] = call_user_func(
                    [static::$FIELDS[$name]['table'], 'create'], $tmp_info[$name]
                );
            } else {
                $this->info[$name] = $tmp_info[$name];
            }
        }
    }
}
