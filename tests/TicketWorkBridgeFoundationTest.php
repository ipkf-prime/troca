<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $path =
            $root
            . '/'
            . $relative;

        $text =
            file_get_contents(
                $path
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $text;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'WorkItemRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketWorkBridgeService.php'
    );

$bridgeRepository =
    $read(
        'public_html/app/Repositories/'
        . 'WorkExternalSourceBridgeRepository.php'
    );

$bridgeService =
    $read(
        'public_html/app/Services/Work/'
        . 'WorkExternalSourceBridgeService.php'
    );

foreach ([
    'WORK_ITEM_CREATE_OUTER_TRANSACTION_AWARE_V1',
    '$ownsTransaction =',
    '!$this->db->inTransaction()',
    'if ($ownsTransaction) {',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Work item transaction marker missing: '
        . $marker
    );
}

$createStart =
    strpos(
        $repository,
        'public function create('
    );

$updateStart =
    $createStart === false
        ? false
        : strpos(
            $repository,
            'public function update(',
            $createStart
        );

$expect(
    $createStart !== false
    && $updateStart !== false
    && $updateStart > $createStart,
    'Unable to isolate WorkItemRepository::create().'
);

$createMethod =
    substr(
        $repository,
        (int) $createStart,
        (int) $updateStart
        - (int) $createStart
    );

$expect(
    !str_contains(
        $createMethod,
        "        \$this->db->beginTransaction();\n\n        try {"
    ),
    'Work item create still unconditionally owns the transaction.'
);

foreach ([
    'TICKET_WORK_CREATE_LINK_FOUNDATION_V1',
    "resolve(\n                'ticketing.primary'",
    "resolve(\n                'work.primary'",
    'new WorkItemRepository(',
    'new WorkExternalSourceBridgeRepository(',
    '->canViewTicket(',
    '->projectAccess(',
    "->bindings(\n                'ticketing',\n                'project'",
    "->linkedItems(\n                    'ticketing',\n                    'ticket'",
    '$this->work->beginTransaction();',
    '$this->workItems->create(',
    "->linkItem(\n                    \$projectReference,\n                    \$itemReference,\n                    'ticketing',\n                    'ticket'",
    "'created_from'",
    '$this->work->commit();',
    '$this->work->rollBack();',
    '->workLaunch(',
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Ticket Work bridge marker missing: '
        . $marker
    );
}

$expect(
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\b\s+ticketing_/i',
        $service
    ) !== 1,
    'Ticket Work bridge must not write Ticketing tables.'
);

foreach ([
    'TSP-NEP',
    'WRK-PRJ-D53B07904826FE67',
    'WPSB-17D8FAEC5ECA73B4680ECA3F',
    'سامانه نهاده پخش',
] as $customerLiteral) {
    $expect(
        !str_contains(
            $service,
            $customerLiteral
        ),
        'Customer literal leaked into generic source: '
        . $customerLiteral
    );
}

foreach ([
    'public function transaction(',
    '$this->db->inTransaction()',
] as $marker) {
    $expect(
        str_contains(
            $bridgeRepository,
            $marker
        ),
        'Bridge transaction contract missing: '
        . $marker
    );
}

foreach ([
    'public function bindings(',
    'public function linkItem(',
    'public function linkedItems(',
] as $marker) {
    $expect(
        str_contains(
            $bridgeService,
            $marker
        ),
        'Bridge service contract missing: '
        . $marker
    );
}

echo "TICKET_WORK_BRIDGE_FOUNDATION_TEST=PASS\n";
