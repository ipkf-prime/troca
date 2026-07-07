<?php

namespace IPKF\Database;

class DatabaseManager
{
    protected array $connections = [];

    public function connection(string $name = 'mysql'): Connection
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = config("database.connections.$name");

        return $this->connections[$name] = new Connection($config);
    }
}