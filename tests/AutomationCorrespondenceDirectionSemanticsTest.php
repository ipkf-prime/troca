<?php

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-form.php'
);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/CorrespondenceCommandService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains(
        $view,
        "\$initialDirection === 'incoming'"
    ),
    'External letter metadata must be incoming-only.'
);

$expect(
    !str_contains(
        $view,
        "\$documentTemplates[0]['public_reference']"
    ),
    'Fresh correspondence form must not auto-select the first template.'
);

$expect(
    str_contains(
        $view,
        "[data-automation-party]"
    )
    && str_contains(
        $view,
        "external_display_name[]"
    )
    && str_contains(
        $view,
        "party_reference_token[]"
    ),
    'Review party count must inspect actual completed party values.'
);

$expect(
    str_contains(
        $service,
        "\$direction === 'incoming'"
    )
    && str_contains(
        $service,
        "'external_number' =>"
    ),
    'Command service must enforce incoming-only external metadata.'
);


$expect(
    str_contains(
        $view,
        "'users' => 'کاربر'"
    )
    && str_contains(
        $view,
        "'persons' => 'شخص'"
    )
    && str_contains(
        $view,
        "'organizations' => 'سازمان'"
    )
    && str_contains(
        $view,
        "'org_units' => 'واحد'"
    ),
    'Internal references must show their reference kind explicitly.'
);

$expect(
    !str_contains(
        $view,
        '<optgroup label='
    ),
    'Internal reference selector must avoid native optgroup rendering.'
);


$expect(
    str_contains(
        $service,
        'Only actual party data makes a row substantive.'
    )
    && str_contains(
        $service,
        '$tokenValue'
    )
    && str_contains(
        $service,
        '$nameValue'
    )
    && str_contains(
        $service,
        '$organizationValue'
    )
    && str_contains(
        $service,
        '$contactValue'
    ),
    'Unused pre-rendered party rows must be ignored by actual party data.'
);


$expect(
    str_contains(
        $view,
        "'receiver_required' =>"
    )
    && str_contains(
        $view,
        'حداقل یک گیرنده اصلی باید مشخص شود.'
    )
    && str_contains(
        $view,
        'data-initial-error-tab='
    ),
    'Correspondence validation errors must be specific and open the relevant tab.'
);

echo "Automation correspondence direction semantic checks passed.\n";
