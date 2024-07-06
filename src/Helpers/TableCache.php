<?php

namespace DigitalStars\AR\Helpers;

use DigitalStars\AR\Exception;
use DigitalStars\AR\Table;

class TableCache {
    private static array $SUPER_CACHE_TABLES = [];

    public static function saveTableCache(Table $item) {
        if ($item->isSetId()) {
            if (!isset(self::$SUPER_CACHE_TABLES[$item::class][$item->getId()])) {
                self::$SUPER_CACHE_TABLES[$item::class][$item->getId()] = $item;
            } else if (self::$SUPER_CACHE_TABLES[$item::class][$item->getId()] !== $item) {
                throw new Exception('Init clone class!!');
            }
        }
    }

    public static function getTableCacheForClassnameAndId(string $class_name, int $id): Table|null {
        if (isset(self::$SUPER_CACHE_TABLES[$class_name][$id]))
            return self::$SUPER_CACHE_TABLES[$class_name][$id];
        return null;
    }

    public static function print_r_super_cache() {
        echo "\n";
        foreach (self::$SUPER_CACHE_TABLES as $class_name => $object_list) {
            /** @var Table $object */
            foreach ($object_list as $id => $object) {
                echo "$class_name -> $id. Is_init: " . ($object->isLoadDataFromDB() ? 1 : 0) . "\n";
            }
        }
        echo "\n";
    }
}