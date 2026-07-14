<?php

require dirname(__DIR__) . '/public_html/vendor/autoload.php';

use App\Services\GeographyCrosswalk\GeographyCrosswalkPolicy;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$fixture = json_decode(
    file_get_contents(__DIR__ . '/fixtures/ministry-sci-geography-crosswalk.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$policy = new GeographyCrosswalkPolicy();
$evaluate = static function (array $scenario) use ($policy): string {
    if (in_array($scenario['source_kind'], [
        'settlement_observation',
        'diag_classified_settlement_observation',
    ], true)) {
        return 'excluded';
    }

    if ($scenario['source_kind'] === 'statistical_urban_unit'
        && $policy->isNumberedStatisticalUrbanUnit($scenario['normalized_title'])
    ) {
        return 'excluded';
    }

    if ($scenario['parent_status'] === 'ambiguous') {
        return 'ambiguous';
    }

    $targets = array_values(array_filter(
        $scenario['targets'],
        static fn (array $target): bool => $target['path'] === $scenario['source_path']
            && $target['normalized_title'] === $scenario['normalized_title']
    ));

    if ($targets === []) {
        return 'unmatched';
    }

    $rawTitleExact = count($targets) === 1
        && $targets[0]['title'] === $scenario['source_title'];

    return $policy->deterministicStatus(
        count($targets),
        $rawTitleExact,
        $scenario['parent_status'] === 'probable',
        $scenario['source_kind'] === 'statistical_urban_unit'
    );
};
$first = [];

foreach ($fixture as $scenario) {
    $actual = $evaluate($scenario);
    $check($actual === $scenario['expected'], "Unexpected fixture outcome: {$scenario['scenario']}");
    $first[$scenario['scenario']] = $actual;
}

$second = [];

foreach ($fixture as $scenario) {
    $second[$scenario['scenario']] = $evaluate($scenario);
}

$check($first === $second, 'Repeated crosswalk policy evaluation must be deterministic.');
$check(
    $first['exact_county_under_matched_province'] === 'exact',
    'Same county titles under different provinces must remain path-scoped.'
);
$check(
    $first['numbered_statistical_city_excluded'] === 'excluded',
    'Numbered statistical urban units must not become official-city candidates.'
);

echo "Ministry SCI geography crosswalk policy fixture: OK\n";
