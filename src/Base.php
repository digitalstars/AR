<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\SimpleSQL\Components\Where;

class Base {
    public array $info = [];
    public ?int $id;
    public bool $is_load_data_from_db = false;
    public array $modify_fields = [];
    public array $cache_select_fields = [];
    public array $update_fields = [];
    public Where $custom_filter_condition;
    public Table $parent;

    public function __construct() {
    }
}
