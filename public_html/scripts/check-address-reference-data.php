<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

$count = static function (
    string $table,
    string $where = '1=1'
) use ($db): int {
    $statement = $db->query(
        "SELECT COUNT(*) FROM {$table} WHERE {$where}"
    );

    return (int) $statement->fetchColumn();
};

$result = [
    'address_types' =>
        $count('address_types', "status = 'active'"),
    'geographic_levels' =>
        $count(
            'geographic_level_types',
            "status = 'active'
             AND code IN ('province','county','district','city')"
        ),
    'provinces' => 0,
    'counties' => 0,
    'cities' => 0,
    'active_relations' =>
        $count(
            'geographic_location_relations',
            "status = 'active'"
        ),
];

$statement = $db->query("
    SELECT levels.code, COUNT(*) AS total
    FROM geographic_locations AS locations
    INNER JOIN geographic_level_types AS levels
      ON levels.id = locations.level_type_id
    WHERE locations.status = 'active'
      AND levels.status = 'active'
      AND levels.code IN (
          'province',
          'county',
          'city'
      )
    GROUP BY levels.code
");

foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $key = match ((string) $row['code']) {
        'province' => 'provinces',
        'county' => 'counties',
        'city' => 'cities',
        default => null,
    };

    if ($key !== null) {
        $result[$key] = (int) $row['total'];
    }
}

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
