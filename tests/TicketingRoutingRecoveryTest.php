<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);
    if (!is_string($content)) {
        throw new RuntimeException('Cannot read: ' . $relative);
    }
    return $content;
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$router = $read(
    'public_html/app/Repositories/'
    . 'TicketCreateRoutingRepository.php'
);
$service = $read(
    'public_html/app/Services/Ticketing/'
    . 'TicketRoutingRecoveryService.php'
);
$foundation = $read(
    'public_html/app/Services/Ticketing/'
    . 'TicketRoutingExceptionService.php'
);
$routes = $read(
    'public_html/routes/ticketing-runtime.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'ticketing-ticket-detail.php'
);

foreach ([
    'TICKETING_ROUTING_RECOVERY_V1',
    'public function recoverMissingTopic(',
    'FOR UPDATE',
    'topicForSelection(',
    'resolveRoute(',
    'fixedAssignee(',
    'leastLoadedAssignee(',
    'roundRobinAssignee(',
    'routing_recovery_no_eligible_assignee',
    'routing_recovery_invalid_topology',
    'ticket_routed',
    'ticket_assigned',
    "'missing_topic_recovery'",
    "'owner'",
] as $marker) {
    $expect(
        str_contains($router, $marker),
        'Router marker missing: ' . $marker
    );
}

$start = strpos(
    $router,
    'public function recoverMissingTopic('
);
$end = strpos(
    $router,
    '    private function resolveRoute(',
    $start === false ? 0 : $start
);

$expect(
    $start !== false
    && $end !== false
    && $end > $start,
    'Recovery method bounds invalid.'
);

$block = substr($router, $start, $end - $start);

$expect(
    !str_contains($block, 'intakeRoute('),
    'Recovery must not call intake fallback.'
);

foreach ([
    'support_topic_id IS NULL',
    'matched_routing_rule_id IS NULL',
    'current_support_layer_id IS NULL',
    'current_support_node_id IS NULL',
    'current_support_queue_id IS NULL',
    'current_support_team_id IS NULL',
    'current_assignee_project_member_id IS NULL',
] as $guard) {
    $expect(
        str_contains($block, $guard),
        'Missing null guard: ' . $guard
    );
}

foreach ([
    'TicketRoutingExceptionService',
    'AuthorizationService',
    'default =>',
    'throw $exception',
    "'ticketing.project.manage'",
    "'missing_topic'",
    'isActiveProjectManager(',
    "'manager'",
    'routing_recovery_no_route',
    'routing_recovery_no_eligible_assignee',
] as $marker) {
    $expect(
        str_contains($service, $marker),
        'Service marker missing: ' . $marker
    );
}

$expect(
    str_contains($foundation, "'legacy_topicless_routed'"),
    'NP-000002 protection contract disappeared.'
);

foreach ([
    'TICKETING_ROUTING_RECOVERY_V1_ROUTE',
    '/recover-routing',
    "'/admin/ticketing/projects'",
    'TicketRoutingRecoveryService',
    'routing_recovery_invalid_csrf',
] as $marker) {
    $expect(
        str_contains($routes, $marker),
        'Route marker missing: ' . $marker
    );
}

foreach ([
    'TICKETING_ROUTING_RECOVERY_V1_UI',
    'TICKETING_ROUTING_RECOVERY_ERROR_TAB_V1',
    'data-ticketing-routing-recovery-form',
    '$routingRecoveryIncident',
    'operationsTab.click();',
    'data-ticketing-routing-recovery-notice',
    'name="support_topic_id"',
    'ثبت موضوع و مسیریابی',
    '$routingExceptionTopics',
    '$routingExceptionDefaultTopic',
    'موتور استاندارد مسیریابی',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        'UI marker missing: ' . $marker
    );
}

$formStart = strpos(
    $view,
    'data-ticketing-routing-recovery-form'
);
$formEnd = strpos(
    $view,
    '<?php else: ?>',
    $formStart === false ? 0 : $formStart
);

$expect(
    $formStart !== false
    && $formEnd !== false
    && $formEnd > $formStart,
    'Recovery form bounds invalid.'
);

$form = substr(
    $view,
    $formStart,
    $formEnd - $formStart
);

foreach ([
    'assignee_id',
    'project_member_id',
    'support_queue_id',
    'support_team_id',
    'support_node_id',
    'support_layer_id',
] as $forbidden) {
    $expect(
        !str_contains(
            $form,
            'name="' . $forbidden . '"'
        ),
        'Manual routing field exposed: ' . $forbidden
    );
}

$requesterTicketHelperStart = strpos(
    $router,
    'private function missingTopicRecoveryTicket('
);

$requesterTicketHelperEnd = strpos(
    $router,
    'private function isFullyUnroutedTicket(',
    $requesterTicketHelperStart === false
        ? 0
        : $requesterTicketHelperStart
);

$expect(
    $requesterTicketHelperStart !== false
    && $requesterTicketHelperEnd !== false
    && $requesterTicketHelperEnd > $requesterTicketHelperStart,
    'Requester recovery helper bounds invalid.'
);

$requesterTicketHelper = substr(
    $router,
    $requesterTicketHelperStart,
    $requesterTicketHelperEnd - $requesterTicketHelperStart
);

$expect(
    str_contains(
        $requesterTicketHelper,
        't.requester_user_reference'
    ),
    'Recovery ticket must load requester_user_reference.'
);

$expect(
    str_contains(
        $block,
        'routing_recovery_requester_identity_missing'
    ),
    'Requester identity fail-closed guard missing.'
);

$expect(
    preg_match(
        '/\\$this->topicForSelection\\(\\s*'
        . '\\$requesterUserReference,\\s*'
        . '\\$projectId,\\s*'
        . '\\$serviceId,\\s*'
        . '\\$topicId\\s*\\)/s',
        $block
    ) === 1,
    'Recovery must validate topic with requester identity.'
);

$expect(
    preg_match(
        '/\\$this->topicForSelection\\(\\s*'
        . '\\$projectId,\\s*'
        . '\\$serviceId,\\s*'
        . '\\$topicId\\s*\\)/s',
        $block
    ) === 0,
    'Wrong three-argument topic validation remains.'
);

$expect(
    str_contains(
        $service,
        "'routing_recovery_requester_identity_missing'"
    ),
    'Requester identity failure mapping missing.'
);

echo "REQUESTER_TOPIC_SCOPE=PASS\\n";
echo "REQUESTER_IDENTITY_FAIL_CLOSED=PASS\\n";

$expect(
    str_contains(
        $view,
        "$routingRecoveryNotice === 'routing_recovery_applied'"
    ),
    'Recovery success-state class condition missing.'
);

$expect(
    str_contains(
        $view,
        "'admin-alert admin-alert--success'"
    ),
    'Recovery success notice must use success styling.'
);

$expect(
    str_contains(
        $view,
        "'admin-alert admin-alert--danger'"
    ),
    'Recovery failure notice must use danger styling.'
);

echo "RECOVERY_NOTICE_SEMANTICS=PASS\n";

echo "TICKETING_ROUTING_RECOVERY_PASS\n";
echo "STANDARD_ROUTE_ONLY=PASS\n";
echo "INTAKE_FALLBACK=ABSENT\n";
echo "FULLY_UNROUTED_GUARD=PASS\n";
echo "CANONICAL_ASSIGNMENT_ROLE=OWNER\n";
echo "PROJECT_MANAGER_SCOPE=PASS\n";
echo "NP000002_PROTECTION=PASS\n";
