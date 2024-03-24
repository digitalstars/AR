<?php

namespace DigitalStars\InterfaceDB\SmartList;

use DigitalStars\InterfaceDB\Exception;

class SmartList implements \ArrayAccess, \IteratorAggregate {
    protected array $list = [];
    protected string $class_name;

    private bool $is_unique;

    public function __construct(string $class_name = null, bool $is_unique = false) {
        if ($class_name)
            $this->class_name = $class_name;
        else
            $this->class_name = SmartListItem::class;

        $this->is_unique = $is_unique;
    }

    public static function create(string $class_name = null, bool $is_unique = false) {
        return new static($class_name, $is_unique);
    }

    public function getIterator() {
        return new \ArrayIterator($this->list);
    }

    public function offsetExists($offset) {
        return isset($this->list[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }

    public function offsetSet($offset, $value) {
        if ($this->is_unique && $this->in($value))
            return;

        if (!($value instanceof $this->class_name))
            throw new Exception('SmartList passed not ' . $this->class_name);
        if (is_null($offset)) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        unset($this->list[$offset]);
    }

    public function clear() {
        unset($this->list);
        $this->list = [];
    }

    public function getIds() {
        $ids = [];
        foreach ($this->list as $v)
            $ids[] = $v->getId();
        return $ids;
    }

    public function count(): int {
        return count($this->list);
    }

    public function empty(): bool {
        return empty($this->list);
    }

    public function shift() {
        return array_shift($this->list);
    }

    public function pop() {
        return array_pop($this->list);
    }

    public function in(SmartListItem $value) {
        return in_array($value->getId(), $this->getIds(), true);
    }

    public function remove(SmartListItem $value): bool {
        /** @var SmartListItem $item */
        foreach ($this->list as $key => $item) {
            if ($item->getId() === $value->getId()) {
                unset($this->list[$key]);
                return true;
            }
        }
        return false;
    }

    public function getFromId(int $id) {
        foreach ($this->list as $e)
            if ($e->getId() === $id)
                return $e;
        return null;
    }

    public function merge(self|array $list): void {
        foreach ($list as $item)
            $this[] = $item;
    }

    public function getItemClassName(): string {
        return $this->class_name;
    }

    public function getIsUnique(): bool {
        return $this->is_unique;
    }

    public function setUnique(bool $is_unique): void {
        $this->is_unique = $is_unique;
    }

    public function setField(string $field, $value): void {
        foreach ($this->list as $item)
            $item->$field = $value;
    }

    public function runMethod(string $method, ...$args) {
        foreach ($this->list as $item)
            $item->$method(...$args);
    }

    /** Сортировка по полю
     * @param string $field - поле, по которому будет производиться сортировка
     * @param bool $direction - направление сортировки (true - по возрастанию, false - по убыванию)
     * @return $this
     */
    public function sortByField(string $field, bool $direction = true): static {
        if ($direction)
            $callback = function($a, $b) use ($field) {
                return $a->$field <=> $b->$field;
            };
        else
            $callback = function($a, $b) use ($field) {
                return $b->$field <=> $a->$field;
            };
        usort($this->list, $callback);
        return $this;
    }

    public function searchByField(string $field, $value, int $max_count = null): static {
        $result = new static($this->class_name);
        $count = 0;
        foreach ($this->list as $item) {
            if ($item->$field === $value) {
                $result[] = $item;
                ++$count;
                if (!is_null($max_count) && $count >= $max_count)
                    break;
            }
        }
        return $result;
    }

    public function searchByCall(string $method, int $max_count = null, ...$args): static {
        $result = new static($this->class_name);
        $count = 0;
        foreach ($this->list as $item) {
            if ($item->$method(...$args)) {
                $result[] = $item;
                ++$count;
                if (!is_null($max_count) && $count >= $max_count)
                    break;
            }
        }
        return $result;
    }

    public function searchByNotCall(string $method, int $max_count = null, ...$args): static {
        $result = new static($this->class_name);
        $count = 0;
        foreach ($this->list as $item) {
            if (!$item->$method(...$args)) {
                $result[] = $item;
                ++$count;
                if (!is_null($max_count) && $count >= $max_count)
                    break;
            }
        }
        return $result;
    }

    public function getSum(string $field): int {
        $sum = 0;
        foreach ($this->list as $item)
            $sum += $item->$field;
        return $sum;
    }

    public function getFields(string $field): array {
        $fields = [];
        foreach ($this->list as $item)
            $fields[] = $item->$field;
        return $fields;
    }

    public function getFieldsObject(string $field, string $object_class_name): static {
        $fields = new static($object_class_name);
        foreach ($this->list as $item)
            $fields[] = $item->$field;
        return $fields;
    }

    public function getCallMethod(string $method, ...$args): array {
        $result = [];
        foreach ($this->list as $item) {
            $response = $item->$method(...$args);
            if (is_array($response) || $response instanceof self)
                array_push($result, ...$response);
            else
                $result[] = $response;
        }
        return $result;
    }

    public function getCallMethodObject(string $method, string $object_class_name, ...$args): static {
        $result = new static($object_class_name);
        foreach ($this->list as $item) {
            $response = $item->$method(...$args);
            if (is_array($response) || $response instanceof self)
                $result->merge($response);
            else
                $result[] = $response;
        }
        return $result;
    }

    public function removeByField(string $field, $value): void {
        foreach ($this->list as $key => $item)
            if ($item->$field === $value)
                unset($this->list[$key]);
    }

    public function removeByCall(string $method, ...$args): void {
        foreach ($this->list as $key => $item)
            if ($item->$method(...$args))
                unset($this->list[$key]);
    }

    public function removeByNotCall(string $method, ...$args): void {
        foreach ($this->list as $key => $item)
            if (!$item->$method(...$args))
                unset($this->list[$key]);
    }
}
