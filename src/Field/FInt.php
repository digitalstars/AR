<?php

namespace DigitalStars\InterfaceDB\Field;

/**
 * @property int|null val
 */
class FInt extends WithoutType {
    const TYPE = 1;

    public function getValue(): ?int {
        return $this->validate(parent::getValue());
    }

    public function setValue(mixed $value): void {
        parent::setValue($this->validate($value));
    }

    private function validate($value): ?int {
        if ($this->ref()->is_required)
            return (int)$value;
        else
            return is_null($value) ? null : (int)$value;
    }
}