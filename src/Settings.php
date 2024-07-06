<?php

namespace DigitalStars\AR;

use DigitalStars\SimpleSQL\Parser;

class Settings {
    private static \PDO $pdo;
    private static \Closure $exec;
    private static \Closure $query;
    private static int $max_depth = 3;
    private static bool $is_lazy_update = true;

    public static function setPDO(\PDO $pdo) {
        self::$pdo = $pdo;
        Parser::setPDO($pdo);
    }

    public static function getPDO(): \PDO {
        return self::$pdo;
    }

    public static function setExec(\Closure $exec) {
        self::$exec = $exec;
    }

    public static function exec(): false|int {
        if (isset(self::$exec))
            return call_user_func_array(self::$exec, func_get_args());

        return self::getPDO()->exec(...func_get_args());
    }

    public static function setQuery(\Closure $query) {
        self::$query = $query;
    }

    public static function query(): false|\PdoStatement {
        if (isset(self::$query))
            return call_user_func_array(self::$query, func_get_args());

        return self::getPDO()->query(...func_get_args());
    }

    public static function setMaxDepth(int $max_depth): void {
        self::$max_depth = $max_depth;
    }

    public static function getMaxDepth(): int {
        return self::$max_depth;
    }

    public static function setIsLazyUpdate(bool $lazy_update): void {
        self::$is_lazy_update = $lazy_update;
    }

    public static function getIsLazyUpdate(): bool {
        return self::$is_lazy_update;
    }

    public static function getUniqueKey(): int {
        static $key = 0;
        return ++$key;
    }
}
