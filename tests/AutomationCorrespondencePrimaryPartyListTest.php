<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/CorrespondenceRepository.php'
    );

$builder =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/CorrespondenceViewModelBuilder.php'
    );

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };

$expect(
    is_string($repository)
    && is_string($builder),
    'Primary-party sources must be readable.'
);

$expect(
    str_contains(
        $repository,
        "WHEN c.direction_code ="
    )
    && str_contains(
        $repository,
        "'incoming'"
    )
    && str_contains(
        $repository,
        "THEN 'sender'"
    )
    && str_contains(
        $repository,
        "ELSE 'primary_recipient'"
    ),
    'Main party must be sender for incoming and primary recipient otherwise.'
);

foreach ([
    'correspondent_target_kind_code',
    'correspondent_person_id',
    'correspondent_organization_id',
    'correspondent_org_unit_id',
    'correspondent_external_display_name',
    'correspondent_external_organization_name',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        "Missing main-party projection: {$needle}"
    );
}

$expect(
    str_contains(
        $builder,
        "'correspondent' => "
        . '$this->listCorrespondent($row)'
    )
    && str_contains(
        $builder,
        'private function listCorrespondent'
    )
    && str_contains(
        $builder,
        'return $this->partyDisplay(['
    ),
    'List display must resolve both internal and external main parties.'
);

echo "Automation correspondence primary-party list checks passed.\n";
