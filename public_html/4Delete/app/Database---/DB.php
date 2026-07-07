<?php

namespace IPKF\Database;

class DB
{
    protected static DatabaseManager $manager;

    public static function setManager(DatabaseManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function connection(string $name = 'mysql'): Connection
    {
        return self::$manager->connection($name);
    }
}