<?php

require dirname(__DIR__) . '/public_html/vendor/autoload.php';

use App\Services\GeographyImport\PersianTextNormalizer;
use App\Services\GeographyImport\StatisticalCenterGeographyCsvParser;
use App\Services\GeographyImport\StatisticalCenterGeographyValidator;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$normalizer = new PersianTextNormalizer();
$parser = new StatisticalCenterGeographyCsvParser($normalizer);
$validator = new StatisticalCenterGeographyValidator($normalizer);
$headers = [
    'province_code' => ['کد استان'],
    'province_title' => ['نام استان'],
    'county_code' => ['کد شهرستان'],
    'county_title' => ['نام شهرستان'],
    'district_code' => ['کد بخش'],
    'district_title' => ['نام بخش'],
    'rural_or_city_code' => ['کد دهستان/ شهر', 'کد دهستان/شهر'],
    'rural_district_title' => ['نام دهستان'],
    'settlement_code' => ['کد آبادی'],
    'source_title' => ['نام'],
    'coderec' => ['CODEREC'],
    'diag' => ['DIAG'],
];
$mappings = [];

foreach ([
    ['1', 'province', 'province_observation', null, 'province_code'],
    ['2', 'county', 'county_observation', '1', 'county_code'],
    ['3', 'district', 'district_observation', '2', 'district_code'],
    ['4', 'rural_district', 'rural_district_observation', '3', 'rural_or_city_code'],
    ['5', null, 'statistical_urban_unit', '3', 'rural_or_city_code'],
    ['6', 'settlement', 'settlement_observation', '4,5,3', 'settlement_code'],
    ['8', 'settlement', 'diag_classified_settlement_observation', '4,5,3', 'settlement_code'],
] as [$type, $level, $kind, $parentType, $codeField]) {
    $mappings[$type] = [
        'source_record_type' => $type,
        'derived_level_code' => $level,
        'source_entity_kind' => $kind,
        'parent_record_type' => $parentType,
        'code_field' => $codeField,
    ];
}

$validateFixture = static function () use ($parser, $validator, $headers, $mappings): array {
    $stream = $parser->stream(
        __DIR__ . '/fixtures/statistical-center-geography-validation.csv',
        $headers
    );
    $rows = [];

    foreach ($stream as $row) {
        $rows[] = $validator->validate($row, $mappings, 'IR-STAT');
    }

    return [$rows, $stream->getReturn()];
};

[$rows, $parserSummary] = $validateFixture();
$check(count($rows) === 15, 'Every nonblank fixture row must be retained.');
$check($parserSummary['blank_rows'] === 1, 'The blank fixture row must be counted.');
$check($rows[0]['source_code'] === '00', 'Leading-zero province code must be preserved.');
$check(
    $rows[6]['source_composite_key'] === 'P:00|C:01|D:01|R:0001|S:000268',
    'Settlement identity must use its full source hierarchy context.'
);
$check($rows[14]['normalized_source_code'] === '000273', 'Persian source digits must normalize to ASCII.');
$check($rows[14]['source_code'] === '۰۰۰۲۷۳', 'Raw Persian source digits must remain preserved.');
$check($rows[10]['row_checksum'] === $rows[11]['row_checksum'], 'Exact observations must have equal checksums.');
$check($rows[12]['row_checksum'] !== $rows[13]['row_checksum'], 'Conflicting titles must have different checksums.');
$check($rows[7]['source_classifier_code'] === '01', 'DIAG must remain an opaque source string.');
$check($rows[4]['derived_level_code'] === null, 'CODEREC 5 must not derive an official city level.');

$issueCodes = [];

foreach ($rows as $row) {
    foreach ($row['issues'] as $issue) {
        $issueCodes[$issue['code']] = true;
    }
}

foreach ([
    'STATISTICAL_URBAN_UNIT',
    'NUMBERED_URBAN_SUBDIVISION',
    'POSSIBLE_OFFICIAL_CITY_CANDIDATE',
    'DIAG_PRESENT',
    'UNKNOWN_CODEREC',
    'UNSUPPORTED_SOURCE_RECORD',
] as $issueCode) {
    $check(isset($issueCodes[$issueCode]), "Missing expected fixture issue: {$issueCode}");
}

[$repeatedRows, $repeatedParserSummary] = $validateFixture();
$check($repeatedRows === $rows, 'Repeated validation must be deterministic.');
$check($repeatedParserSummary === $parserSummary, 'Repeated parsing must be deterministic.');

echo "Statistical Center geography validation fixture: OK\n";
