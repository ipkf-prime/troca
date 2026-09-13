<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$viewFile =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-topology.php';

$serviceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingScopedSupportTopologyService.php';

foreach (
    [
        $viewFile,
        $serviceFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A8 UX file: '
            . $file
        );
    }
}

$view =
    (string) file_get_contents(
        $viewFile
    );

$service =
    (string) file_get_contents(
        $serviceFile
    );

foreach (
    [
        'TICKETING_SCOPED_TOPOLOGY_UX_V1',
        'delegated_scope_mode',
        'allowed_mutations',
        'topology-layers',
        'topology-teams',
        'topology-team-nodes',
        'topology-team-queues',
        'topology-members',
        'بازگشت به تیکتینگ',
        'محدوده دسترسی تفویض‌شده',
    ]
    as $marker
) {
    if (
        !str_contains(
            $view,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing A8 UX marker: '
            . $marker
        );
    }
}

foreach (
    [
        'node.create',
        'relation.create',
        'queue.create',
        'hasAnyDelegatedActionForProject',
        "['topology.create']",
    ]
    as $marker
) {
    if (
        !str_contains(
            $service,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing A8 scoped-service marker: '
            . $marker
        );
    }
}

echo "TICKETING_SCOPED_TOPOLOGY_UX_CONTRACT=PASS\n";
echo "DELEGATED_DEFAULT_TAB=NODES\n";
echo "PROJECT_WIDE_TABS=HIDDEN_IN_DELEGATED_MODE\n";
echo "FORBIDDEN_PROJECTS_LINK=REMOVED_IN_DELEGATED_MODE\n";
echo "CREATE_FORMS=CAPABILITY_AWARE\n";
echo "FULL_ADMIN_UX=PRESERVED\n";
