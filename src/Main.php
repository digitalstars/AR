<?php

namespace DigitalStars\InterfaceDB;

use DigitalStars\SimpleSQL\Parser;

class Main {
    private static \PDO $pdo;
    private static \Closure $exec;
    private static \Closure $query;
    private static int|null $max_depth = null;

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

    public static function setMaxDepth(?int $max_depth = null): void {
        self::$max_depth = $max_depth;
    }

    public static function getMaxDepth(): int|null {
        return self::$max_depth;
    }
}
