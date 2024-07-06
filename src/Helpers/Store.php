<?php

namespace DigitalStars\AR\Helpers;

use DigitalStars\SimpleSQL\Components\Where;
use DigitalStars\AR\Table;

class Store {
    public array $info = [];
    public bool $is_load_data_from_db = false;
    public array $modify_fields = [];
    public array $update_fields = [];
    public Where $custom_filter_condition;
    public array $custom_filter_order_by;
    public Table $parent;

    public function __construct() {
    }
}
