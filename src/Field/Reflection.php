<?php

namespace DigitalStars\AR\Field;

use DigitalStars\AR\Table;
use DigitalStars\AR\VirtualTable;

class Reflection {

    /**
     * @var string Плейсхолдер для типа поля для БД
     */
    public readonly string $placeholder;
    /**
     * @var bool Является ли поле таблицей
     */
    public readonly bool $is_table;

    /**
     * @param string $select_name Имя поля в селекте вместе с alias-ом (alias_name)
     * @param string $query_name Имя поля вместе с alias-ом (alias.name)
     * @param string $db_name Чистое имя поля из БД
     * @param string $name Имя поля внутри класса Table
     * @param int|null $type Тип
     * @param bool $is_required Обязательно ли
     * @param bool $is_access_modify Можно ли менять после инициализации
     * @param Table|null $parent Таблица, которой принадлежит этот объект
     */
    public function __construct(
        public readonly string                  $select_name,
        public readonly string                  $query_name,
        public readonly string                  $db_name,
        public readonly string                  $name,
        public readonly int|null                $type,
        public readonly bool                    $is_required,
        public readonly bool                    $is_access_modify,
        public readonly Table|null|VirtualTable $parent
    ) {
        $this->placeholder = self::TypeToPlaceholder($this->type);
        $this->is_table = $this->type === FLink::TYPE;
    }

    private static function TypeToPlaceholder(int $type) {
        if ($type === FInt::TYPE || $type === FBool::TYPE || $type === FLink::TYPE)
            return '?i';
        if ($type === FDouble::TYPE)
            return '?d';
        if ($type === FText::TYPE)
            return '?s';
        return '?n';
    }
}