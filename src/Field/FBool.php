<?php

namespace DigitalStars\AR\Field;

/**
 * @property bool|null val
 */
class FBool extends WithoutType {
    const TYPE = 2;

    public function getValue(): ?bool {
        return $this->validate(parent::getValue());
    }

    public function setValue(mixed $value): void {
        parent::setValue($this->validate($value));
    }

    private function validate($value): ?bool {
        if ($this->ref()->is_required)
            return (bool)$value;
        else
            return is_null($value) ? null : (bool)$value;
    }
}