<?php

namespace DigitalStars\InterfaceDB;

trait Tools {
    private function fieldValidateType(string $name, $value) {
        if (static::$FIELDS[$name]['type'] === 'bool') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (bool)$value;
            else
                return is_null($value) ? null : (bool)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'int') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (int)$value;
            else
                return is_null($value) ? null : (int)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'double') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (double)$value;
            else
                return is_null($value) ? null : (double)$value;
        }

        if (static::$FIELDS[$name]['type'] === 'string') {
            if (!empty(static::$FIELDS[$name]['is_required']))
                return (string)$value;
            else
                return is_null($value) ? null : (string)$value;
        }

        if (!empty(static::$FIELDS[$name]['type'])) {
            $class_name = static::$FIELDS[$name]['type'];

            if (is_null($value) && !empty(static::$FIELDS[$name]['is_required']))
                throw new Exception("$name Not access NULL");

            if (!is_null($value) && !is_a($value, $class_name))
                throw new Exception("$name Not object class " . static::$FIELDS[$name]['type']);

            return $value;
        }

        throw new Exception("$name Not found in " . get_class($this));
    }
}