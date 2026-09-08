<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $text =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable: '
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


$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingAutoClosePolicyFoundation.php'
    );

$policyRepository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketAutoClosePolicyRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAutoCloseService.php'
    );

$job =
    $read(
        'public_html/app/Scheduler/'
        . 'TicketAutoCloseJob.php'
    );

$factory =
    $read(
        'public_html/app/Scheduler/'
        . 'TicketingSchedulerRegistryFactory.php'
    );

$lifecycle =
    $read(
        'public_html/app/Repositories/'
        . 'TicketLifecycleTransitionRepository.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'TICKETING_AUTO_CLOSE_POLICY_FOUNDATION_V1',
    'ticketing_auto_close_policies',
    'support_project_id',
    'is_enabled',
    'DEFAULT 0',
    'delay_hours',
    'eligible_resolved_from',
    'enabled_at',
    'ticketing_auto_close_policy_project_unique',
    'ticketing_auto_close_policy_project_fk',
    'ticketing_support_projects(id)',
] as $marker) {
    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Migration marker missing: '
        . $marker
    );
}


foreach ([
    'public function policyForProject(',
    'public function candidates(',
    "'is_enabled' => false",
    "'delay_hours' => null",
    "'eligible_resolved_from' =>",
    "status_code = 'resolved'",
    'closed_at IS NULL',
    'archived_at IS NULL',
    'resolved_at >= ?',
    'resolved_at <= ?',
] as $marker) {
    $expect(
        str_contains(
            $policyRepository,
            $marker
        ),
        'Policy repository marker missing: '
        . $marker
    );
}


foreach ([
    'system:ticketing-auto-close',
    'policy_disabled',
    'auto_close_policy_delay_invalid',
    'auto_close_policy_eligibility_missing',
    'auto_close_policy_eligibility_invalid',
    'closeResolvedBySystem(',
    'auto_close_ticket_not_due',
    'candidate_count',
    'closed_count',
    'skipped_count',
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Auto-close service marker missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_AUTO_CLOSE_SYSTEM_TRANSITION_V1',
    'public function closeResolvedBySystem(',
    "!==\n                'system:ticketing-auto-close'",
    'FOR UPDATE',
    "status_code =\n                            'closed'",
    'closed_at =',
    'UTC_TIMESTAMP()',
    "'ticket_closed'",
    "'assignment_preserved' =>",
    "'routing_preserved' =>",
    "'actor_type' =>",
    "'system'",
    "'automation_key' =>",
    "'ticketing.ticket.auto_close'",
    'auto_close_project_mismatch',
    'auto_close_policy_not_found',
    'auto_close_policy_disabled',
    'auto_close_policy_changed',
    'auto_close_ticket_not_resolved',
    'auto_close_ticket_not_due',
    "'cutoff_at' =>",
    'ticketing_auto_close_policies',
    'resolved_at >= ?',
    'resolved_at <= ?',
    'auto_close_transition_conflict',
] as $marker) {
    $expect(
        str_contains(
            $lifecycle,
            $marker
        ),
        'System lifecycle marker missing: '
        . $marker
    );
}


foreach ([
    'implements SchedulerJobInterface',
    "'ticketing.ticket.auto_close'",
    "'ticketing'",
    "'project'",
    'defaultIntervalMinutes',
    'ticketing_support_projects',
    'TicketAutoCloseService',
] as $marker) {
    $expect(
        str_contains(
            $job,
            $marker
        ),
        'Scheduler Job marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $factory,
        'new TicketingSlaJob()'
    )
    &&
    str_contains(
        $factory,
        'new TicketAutoCloseJob()'
    ),
    'Ticketing Scheduler registry must contain SLA and Auto-close jobs.'
);


$expect(
    str_contains(
        $registry,
        'CreateTicketingAutoClosePolicyFoundation::class'
    ),
    'Auto-close migration is not registered.'
);


/*
 * Critical first-run safety contract.
 */
$expect(
    str_contains(
        $policyRepository,
        'resolved_at >= ?'
    )
    &&
    str_contains(
        $service,
        'policy_disabled'
    )
    &&
    str_contains(
        $service,
        'auto_close_policy_eligibility_missing'
    ),
    'First-run safety contract is incomplete.'
);


echo
    "TICKETING_AUTO_CLOSE_FOUNDATION_PASS\n";
