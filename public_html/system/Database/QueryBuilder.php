<?php

namespace IPKF\Database;

use PDO;

class QueryBuilder
{
    public function __construct(protected PDO $connection)
    {
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
