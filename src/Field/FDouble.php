<?php

namespace DigitalStars\AR\Field;

/**
 * @property float|null val
 */
class FDouble extends WithoutType {
    const TYPE = 3;

    public function getValue(): ?float {
        return $this->validate(parent::getValue());
    }

    public function setValue(mixed $value): void {
        parent::setValue($this->validate($value));
    }

    private function validate($value): ?float {
        if ($this->ref()->is_required)
            return (double)$value;
        else
            return is_null($value) ? null : (double)$value;
    }
}