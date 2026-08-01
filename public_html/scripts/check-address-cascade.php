<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

$relationType = $db->prepare("
    SELECT id
    FROM geographic_relation_types
    WHERE code = 'administrative_parent'
      AND status = 'active'
    LIMIT 1
");
$relationType->execute();
$relationTypeId = (int) $relationType->fetchColumn();

$counts = [
    'administrative_parent_relation_type_id' =>
        $relationTypeId,
    'primary_administrative_relations' => 0,
    'counties_with_province_parent' => 0,
    'counties_without_province_parent' => 0,
    'cities_with_county_parent' => 0,
    'cities_without_county_parent' => 0,
];

if ($relationTypeId > 0) {
    $statement = $db->prepare("
        SELECT COUNT(*)
        FROM geographic_location_relations
        WHERE relation_type_id = ?
          AND is_primary = 1
          AND status = 'active'
    ");
    $statement->execute([$relationTypeId]);
    $counts['primary_administrative_relations'] =
        (int) $statement->fetchColumn();

    $statement = $db->prepare("
        SELECT
            child_levels.code AS child_level,
            parent_levels.code AS parent_level,
            COUNT(*) AS total
        FROM geographic_location_relations AS relations
        INNER JOIN geographic_locations AS children
          ON children.id = relations.child_location_id
        INNER JOIN geographic_level_types AS child_levels
          ON child_levels.id = children.level_type_id
        INNER JOIN geographic_locations AS parents
          ON parents.id = relations.parent_location_id
        INNER JOIN geographic_level_types AS parent_levels
          ON parent_levels.id = parents.level_type_id
        WHERE relations.relation_type_id = ?
          AND relations.is_primary = 1
          AND relations.status = 'active'
          AND children.status = 'active'
          AND parents.status = 'active'
        GROUP BY
            child_levels.code,
            parent_levels.code
    ");
    $statement->execute([$relationTypeId]);

    foreach (
        $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
        as $row
    ) {
        $child = (string) $row['child_level'];
        $parent = (string) $row['parent_level'];
        $total = (int) $row['total'];

        if (
            $child === 'county'
            && $parent === 'province'
        ) {
            $counts['counties_with_province_parent'] +=
                $total;
        }

        if (
            $child === 'city'
            && $parent === 'district'
        ) {
            $counts['cities_with_county_parent'] +=
                $total;
        }
    }

    $countyTotal = (int) $db->query("
        SELECT COUNT(*)
        FROM geographic_locations AS locations
        INNER JOIN geographic_level_types AS levels
          ON levels.id = locations.level_type_id
        WHERE locations.status = 'active'
          AND levels.code = 'county'
    ")->fetchColumn();

    $cityTotal = (int) $db->query("
        SELECT COUNT(*)
        FROM geographic_locations AS locations
        INNER JOIN geographic_level_types AS levels
          ON levels.id = locations.level_type_id
        WHERE locations.status = 'active'
          AND levels.code = 'city'
    ")->fetchColumn();

    $counts['counties_without_province_parent'] =
        max(
            0,
            $countyTotal
                - $counts[
                    'counties_with_province_parent'
                ]
        );
    $counts['cities_without_county_parent'] =
        max(
            0,
            $cityTotal
                - $counts[
                    'cities_with_county_parent'
                ]
        );
}

echo json_encode(
    $counts,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
