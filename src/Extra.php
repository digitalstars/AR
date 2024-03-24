<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\SimpleSQL\Components\From;

trait Extra {
    protected static array $cache_select_fields = [];
    protected static From $FROM;
}
