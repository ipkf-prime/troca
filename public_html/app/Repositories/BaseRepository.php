<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

abstract class BaseRepository implements RepositoryInterface
{
    protected ?PDO $connection = null;

    protected function connection(): PDO
    {
        return $this->connection ??= Database::connect();
    }
}
