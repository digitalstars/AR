<?php

namespace DigitalStars\InterfaceDB;

trait Tools {
    private function fieldValidateCustom($name, $value): bool {
        if (empty(static::$FIELDS[$name]['validate']))
            return true;

        $raw_value = $value instanceof self ? $value->getId() : $value;

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
}