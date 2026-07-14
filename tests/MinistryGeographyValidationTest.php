<?php

require dirname(__DIR__) . '/public_html/vendor/autoload.php';

use App\Services\GeographyImport\MinistryGeographyCsvParser;
use App\Services\GeographyImport\MinistryGeographyValidator;
use App\Services\GeographyImport\PersianTextNormalizer;

$normalizer = new PersianTextNormalizer();
$parser = new MinistryGeographyCsvParser($normalizer);
$validator = new MinistryGeographyValidator($normalizer);
$parsed = $parser->parse(__DIR__ . '/fixtures/ministry-geography-validation.csv');
$mappings = [
    ['source_type_value' => 'استان', 'geographic_level_code' => 'province', 'parent_geographic_level_code' => null, 'expected_code_length' => 2, 'parent_prefix_length' => null],
    ['source_type_value' => 'شهرستان', 'geographic_level_code' => 'county', 'parent_geographic_level_code' => 'province', 'expected_code_length' => 4, 'parent_prefix_length' => 2],
    ['source_type_value' => 'بخش', 'geographic_level_code' => 'district', 'parent_geographic_level_code' => 'county', 'expected_code_length' => 6, 'parent_prefix_length' => 4],
    ['source_type_value' => 'دهستان', 'geographic_level_code' => 'rural_district', 'parent_geographic_level_code' => 'district', 'expected_code_length' => 8, 'parent_prefix_length' => 6],
    ['source_type_value' => 'شهر', 'geographic_level_code' => 'city', 'parent_geographic_level_code' => 'district', 'expected_code_length' => 9, 'parent_prefix_length' => 6],
];
$result = $validator->validate($parsed['rows'], $mappings, ['11'], 'IR');

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$check(count($result['rows']) === 14, 'Every nonblank fixture row must be retained.');
$check($parsed['blank_rows'] === 1, 'The blank fixture row must be counted.');
$check($result['rows'][4]['source_code'] === '010101001', 'Persian digits and leading zeroes must normalize safely.');
$check($result['rows'][4]['normalized_title'] === 'شهر نمونه کی', 'Arabic Persian characters must normalize for comparison.');
$check($result['rows'][4]['raw_payload']['district_title'] === '11', 'The raw placeholder must be preserved.');
$check($result['rows'][4]['raw_payload']['interpreted_descriptive_fields']['district_title'] === null, 'The configured placeholder must normalize to null.');

foreach ([
    'PLACEHOLDER_VALUE_IGNORED',
    'MISSING_HIERARCHY_CODE',
    'INVALID_CODE_LENGTH',
    'DUPLICATE_HIERARCHY_CODE',
    'MISSING_PARENT_CODE',
    'DUPLICATE_NATIONAL_IDENTIFIER',
    'IDENTIFIER_TITLE_VARIATION',
    'IDENTIFIER_PARENT_VARIATION',
] as $issueCode) {
    $check(isset($result['issue_counts'][$issueCode]), "Missing expected fixture issue: {$issueCode}");
}

$repeat = $validator->validate($parsed['rows'], $mappings, ['11'], 'IR');
$check($repeat['issue_counts'] === $result['issue_counts'], 'Repeated validation must be deterministic.');
$check($repeat['counts'] === $result['counts'], 'Repeated validation counts must be deterministic.');

echo "Ministry geography validation fixture: OK\n";
