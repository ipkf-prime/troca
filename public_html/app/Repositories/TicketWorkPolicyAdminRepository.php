<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

/**
 * TICKET_WORK_POLICY_ADMIN_REPOSITORY_V1
 *
 * Administrative persistence for the two canonical Ticket<->Work policy
 * tables. Table names are fixed internal schema identifiers; editable columns
 * are discovered from the live Work schema and strictly whitelisted.
 */
final class TicketWorkPolicyAdminRepository
{
    private PDO $work;

    private const TABLES = [
        'destination' => 'work_ticket_destination_rules',
        'access' => 'work_ticket_access_rules',
        'lifecycle' => 'work_ticket_lifecycle_sync_rules',
    ];

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->work =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve('work.primary');
    }

    public function schema(string $kind): array
    {
        $table = $this->table($kind);

        $rows =
            $this->work
                ->query(
                    'SHOW COLUMNS FROM '
                    . $this->identifier($table)
                )
                ->fetchAll(PDO::FETCH_ASSOC)
                ?: [];

        if ($rows === []) {
            throw new RuntimeException(
                'Ticket Work policy table schema is unavailable.'
            );
        }

        return $rows;
    }

    public function rows(string $kind): array
    {
        $table = $this->table($kind);
        $columns = $this->columnNames($kind);

        $order = [];

        if (in_array('rule_priority', $columns, true)) {
            $order[] = $this->identifier('rule_priority') . ' DESC';
        }

        if (in_array('priority', $columns, true)) {
            $order[] = $this->identifier('priority') . ' DESC';
        }

        if (in_array('id', $columns, true)) {
            $order[] = $this->identifier('id') . ' DESC';
        }

        $sql =
            'SELECT * FROM '
            . $this->identifier($table)
            . ($order !== []
                ? ' ORDER BY ' . implode(', ', $order)
                : '')
            . ' LIMIT 250';

        return
            $this->work
                ->query($sql)
                ->fetchAll(PDO::FETCH_ASSOC)
                ?: [];
    }

    public function find(string $kind, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $columns = $this->columnNames($kind);

        if (!in_array('id', $columns, true)) {
            return null;
        }

        $statement =
            $this->work->prepare(
                'SELECT * FROM '
                . $this->identifier($this->table($kind))
                . ' WHERE id = ? LIMIT 1'
            );

        $statement->execute([$id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? $row
            : null;
    }

    public function insert(
        string $kind,
        array $values
    ): int {
        $table = $this->table($kind);
        $allowed = $this->columnNames($kind);

        $values =
            array_intersect_key(
                $values,
                array_flip($allowed)
            );

        unset($values['id']);

        if ($values === []) {
            throw new RuntimeException(
                'Ticket Work policy insert has no fields.'
            );
        }

        $columns = array_keys($values);

        $sql =
            'INSERT INTO '
            . $this->identifier($table)
            . ' ('
            . implode(
                ', ',
                array_map(
                    fn (string $column): string =>
                        $this->identifier($column),
                    $columns
                )
            )
            . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?'))
            . ')';

        $statement = $this->work->prepare($sql);
        $statement->execute(array_values($values));

        return (int) $this->work->lastInsertId();
    }

    public function update(
        string $kind,
        int $id,
        array $values
    ): void {
        if ($id < 1) {
            throw new RuntimeException(
                'Ticket Work policy id is invalid.'
            );
        }

        $allowed = $this->columnNames($kind);

        $values =
            array_intersect_key(
                $values,
                array_flip($allowed)
            );

        unset($values['id']);

        if ($values === []) {
            throw new RuntimeException(
                'Ticket Work policy update has no fields.'
            );
        }

        $sets = [];

        foreach (array_keys($values) as $column) {
            $sets[] =
                $this->identifier($column)
                . ' = ?';
        }

        $statement =
            $this->work->prepare(
                'UPDATE '
                . $this->identifier($this->table($kind))
                . ' SET '
                . implode(', ', $sets)
                . ' WHERE id = ?'
            );

        $params = array_values($values);
        $params[] = $id;

        $statement->execute($params);
    }

    public function setActive(
        string $kind,
        int $id,
        bool $active
    ): void {
        $columns = $this->columnNames($kind);
        $values = [];

        if (in_array('is_active', $columns, true)) {
            $values['is_active'] = $active ? 1 : 0;
        }

        if (in_array('enabled', $columns, true)) {
            $values['enabled'] = $active ? 1 : 0;
        }

        if (in_array('status', $columns, true)) {
            $values['status'] = $active ? 'active' : 'inactive';
        }

        if (in_array('status_code', $columns, true)) {
            $values['status_code'] = $active ? 'active' : 'inactive';
        }

        if (in_array('archived_at', $columns, true)) {
            $values['archived_at'] =
                $active
                    ? null
                    : gmdate('Y-m-d H:i:s');
        }

        if ($values === []) {
            throw new RuntimeException(
                'Ticket Work policy table has no reversible active-state column.'
            );
        }

        $this->update($kind, $id, $values);
    }

    public function workProjects(): array
    {
        return
            $this->work
                ->query(
                    "SELECT
                        id,
                        public_reference,
                        code,
                        title
                     FROM work_projects
                     WHERE status_code = 'active'
                       AND archived_at IS NULL
                     ORDER BY title,id"
                )
                ->fetchAll(PDO::FETCH_ASSOC)
                ?: [];
    }

    public function workStatuses(): array
    {
        return
            $this->work
                ->query(
                    "SELECT
                        id,
                        code,
                        title,
                        is_closed
                     FROM work_statuses
                     WHERE is_active = 1
                     ORDER BY sort_order,id"
                )
                ->fetchAll(PDO::FETCH_ASSOC)
                ?: [];
    }

    public function connection(): PDO
    {
        return $this->work;
    }

    private function table(string $kind): string
    {
        $kind = trim($kind);

        if (!array_key_exists($kind, self::TABLES)) {
            throw new RuntimeException(
                'Ticket Work policy kind is invalid.'
            );
        }

        return self::TABLES[$kind];
    }

    private function columnNames(string $kind): array
    {
        return
            array_values(
                array_filter(
                    array_map(
                        static fn (array $column): string =>
                            (string) ($column['Field'] ?? ''),
                        $this->schema($kind)
                    ),
                    static fn (string $column): bool =>
                        $column !== ''
                )
            );
    }

    private function identifier(string $value): string
    {
        if (
            preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'Unsafe SQL identifier.'
            );
        }

        return '`' . $value . '`';
    }
}
