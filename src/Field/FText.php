<?php

namespace DigitalStars\InterfaceDB\Field;

/**
 * @property string|null val
 */
class FText extends WithoutType {
    const TYPE = 4;

    public function getValue(): ?string {
        return $this->validate(parent::getValue());
    }

    public function setValue(mixed $value): void {
        parent::setValue($this->validate($value));
    }

    private function validate($value): ?string {
        if ($this->ref()->is_required)
            return (string)$value;
        else
            return is_null($value) ? null : (string)$value;
    }
}