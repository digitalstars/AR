<?php

namespace DigitalStars\AR\Helpers;

use DigitalStars\AR\Exception;
use DigitalStars\AR\Field\FLink;
use DigitalStars\AR\Field\WithoutType;

trait TableBase {
    public function __set($name, $value) {
        if (empty(static::$FIELDS[$name]) || static::$FIELDS[$name]['type'] !== FLink::TYPE)
            throw new Exception('Maybe usage `->val`?');
        $this->setField($name, $value);
    }

    public function __get($name) {
        return $this->getField($name);
    }

    public function __serialize(): array {
        $result = [];
        $usedRows = [];
        foreach (static::$FIELDS as $name => $info) {
            $value = $this->getField($name);
            if ($this->isTableField($name)) {
                if ($value->isLoadDataFromDB() && $value->isSetId() && empty($usedRows[$value::class][$value->getId()])) {
                    $usedRows[$value::class][$value->getId()] = true;
                    $value = $value->__serialize();
                } else {
                    $value = $value->getId();
                }
            } else {
                $value = $value->val;
            }
            $result[$name] = $value;
        }
        return $result;
    }

    public function jsonSerialize() {
        return $this->__serialize();
    }

    public function getField(string $name): self|WithoutType {
        if (empty(static::$FIELDS[$name]))
            throw new Exception("Field $name is not found in: " . static::class);

        if ($this->isTableField($name)) {
            return $this->getFieldRaw($name);
        }

        if (empty($this->fields[$name])) {
            $this->createEmptyField($name);
        }

        return $this->fields[$name];
    }

    public function raw(): ?int {
        return $this->getId();
    }
}