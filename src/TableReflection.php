<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\SimpleSQL\Components\From;

class TableReflection {
    public readonly string $from;
    public readonly From $from_raw;
    public readonly string $alias;
    public readonly string $id;
    public readonly string $query_id;

    public static function create(From $from, string $id): self {
        $self = new self();

        $self->from_raw = $from;
        $self->loadProperty($id);

        return $self;
    }

    private function loadProperty(string $id): void {
        $this->from = $this->from_raw->getSql();
        $this->alias = $this->from_raw->getAlias();
        $this->id = "$this->alias.$id";
        $this->query_id = "{$this->alias}_$id";
    }
}