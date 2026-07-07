<?php

namespace IPKF\Database;

class DBHelper
{
    public static function table(string $table): QueryBuilder
    {
        return (new QueryBuilder())->table($table);
    }
}