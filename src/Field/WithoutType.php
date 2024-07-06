<?php

namespace DigitalStars\AR\Field;

use DigitalStars\AR\Exception;
use DigitalStars\AR\SmartList\SmartListItemValue;
use DigitalStars\AR\Table;
use DigitalStars\AR\VirtualTable;

/**
 * @property mixed val
 */
class WithoutType implements SmartListItemValue, \Stringable {

    private Reflection $ref;
    private null|Table|VirtualTable $table = null;

    const TYPE = null;

    public function __construct(
        string $select_name,
        string $query_name,
        string $db_name,
        string $name,
        bool   $is_required,
        bool   $is_access_modify,
        null|Table|VirtualTable $table = null
    ) {
        $this->ref = new Reflection($select_name, $query_name, $db_name, $name, static::TYPE, $is_required, $is_access_modify, $table);

        $this->table = $table;
    }

    public function ref(): Reflection {
        return $this->ref;
    }

    public function raw(): mixed {
        return $this->getValue();
    }

    public function default() {
        return $this->table->getFieldDefaultValue($this->ref->name);
    }

    /**
     * @param int|null $type
     * @param string $select_name
     * @param string $query_name
     * @param string $db_name
     * @param string $name
     * @param bool $is_required
     * @param bool $is_access_modify
     * @param $val_prototype
     * @param Table|null $table
     * @return FInt|FBool|FDouble|FText|FLink
     * @throws Exception
     */
    public static function create(?int $type, string $select_name, string $query_name, string $db_name, string $name, bool $is_required, bool $is_access_modify, null|Table|VirtualTable $table = null): FInt|FBool|FDouble|FText|FLink {
        if ($type === FBool::TYPE)
            return new FBool($select_name, $query_name, $db_name, $name, $is_required, $is_access_modify, $table);
        else if ($type === FDouble::TYPE)
            return new FDouble($select_name, $query_name, $db_name, $name, $is_required, $is_access_modify, $table);
        else if ($type === FInt::TYPE)
            return new FInt($select_name, $query_name, $db_name, $name, $is_required, $is_access_modify, $table);
        else if ($type === FText::TYPE)
            return new FText($select_name, $query_name, $db_name, $name, $is_required, $is_access_modify, $table);
        else if ($type === FLink::TYPE)
            return new FLink($select_name, $query_name, $db_name, $name, $is_required, $is_access_modify, $table);

        throw new Exception();
    }

    public function __get($name) {
        if ($name !== 'val')
            throw new Exception('Virtual property: Val only');

        return $this->getValue();
    }

    public function __set($name, $value) {
        if ($name !== 'val')
            throw new Exception('Virtual property: Val only');

        $this->setValue($value);
    }

    public function __isset($name) {
        return $name === 'val';
    }

    public function getValue(): mixed {
        return $this->table->getFieldValue($this->ref->name);
    }

    public function setValue(mixed $value): void {
        if (!$this->table)
            throw new Exception('Set value is not support');

        $this->table->setField($this->ref->name, $value);
    }

    public function __toString(): string {
        return $this->getValue();
    }
}