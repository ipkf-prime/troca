<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $content;
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
        . 'SupportTopicRoutingAdminRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'SupportTopicRoutingAdminService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-routing.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/'
        . 'ticketing.css'
    );

foreach ([
    'TICKETING_SUPPORT_TOPIC_GOVERNANCE_V1',
    'public function topicImpact(',
    'public function topicChildren(',
    'public function topicWouldCreateCycle(',
    'public function createTopicGoverned(',
    'public function updateTopicGoverned(',
    'private function lockTopicDefaultScope(',
    'private function clearTopicDefaultScope(',
    'active_routing_rule_count',
    'open_ticket_count',
    'service_id <=> ?',
    'FOR UPDATE',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Repository governance marker missing: '
        . $marker
    );
}

foreach ([
    "if (\$action === 'topic.update')",
    '$this->routing->createTopicGoverned([',
    'private function updateTopic(',
    'اثر تغییر ساختاری را بررسی کردم',
    'موضوع پیش‌فرض باید فعال و قابل انتخاب باشد.',
    'موضوع جدید بسازید و موضوع قبلی را غیرفعال کنید.',
    'topicWouldCreateCycle(',
    'topicChildren(',
    'updateTopicGoverned(',
    'service(',
    '$projectId,',
    '$serviceId',
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service governance marker missing: '
        . $marker
    );
}

foreach ([
    "'confirm_impact' =>",
    "'status' =>",
] as $marker) {
    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Route governance input missing: '
        . $marker
    );
}

foreach ([
    'data-ticketing-topic-governance',
    'data-ticketing-topic-edit-form',
    'data-ticketing-topic-impact',
    'value="topic.update"',
    'name="ticketing-topic-governance"',
    'ticketing-topic-governance__readonly-field',
    'اثر فعلی این موضوع',
    'حذف فیزیکی موضوع استفاده‌شده انجام نمی‌شود',
    "'topic-updated'",
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'View governance marker missing: '
        . $marker
    );
}

foreach ([
    'TICKETING_SUPPORT_TOPIC_GOVERNANCE_STYLES_V1',
    'TICKETING_TOPIC_GOVERNANCE_UI_POLISH_V1',
    '.ticketing-topic-governance',
    '.ticketing-topic-governance__item',
    '.ticketing-topic-governance__impact',
    '.ticketing-topic-governance__confirm',
    '.ticketing-topic-governance__readonly-field',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Governance CSS marker missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $view,
        'پس از ایجاد تغییر نمی‌کند.'
    ),
    'Obsolete internal-code helper text must be removed.'
);

$expect(
    str_contains(
        $view,
        'name="ticketing-topic-governance"'
    ),
    'Single-open accordion contract missing.'
);

$expect(
    !str_contains(
        $service,
        'DELETE FROM ticketing_support_topics'
    )
    && !str_contains(
        $repository,
        'DELETE FROM ticketing_support_topics'
    ),
    'Physical topic deletion must not be introduced.'
);

$updateStart =
    strpos(
        $repository,
        'public function updateTopicGoverned('
    );

$updateEnd =
    strpos(
        $repository,
        'private function lockTopicDefaultScope(',
        $updateStart === false
            ? 0
            : $updateStart
    );

$expect(
    $updateStart !== false
    && $updateEnd !== false
    && $updateEnd > $updateStart,
    'Governed update bounds invalid.'
);

$updateBlock =
    substr(
        $repository,
        $updateStart,
        $updateEnd - $updateStart
    );

foreach ([
    'code =',
    'public_reference =',
    'support_topic_title_snapshot',
    'ticketing_tickets SET',
] as $forbidden) {
    $expect(
        !str_contains(
            $updateBlock,
            $forbidden
        ),
        'Governed topic update mutates forbidden data: '
        . $forbidden
    );
}

echo "TICKETING_SUPPORT_TOPIC_GOVERNANCE_PASS\n";
echo "TOPIC_EDIT=YES\n";
echo "IMPACT_CONTROL=YES\n";
echo "PHYSICAL_DELETE=NO\n";
echo "DEFAULT_ADMIN_FLOW=SERIALIZED\n";
echo "CODE_IMMUTABLE=YES\n";
echo "TICKET_SNAPSHOTS_PRESERVED=YES\n";
