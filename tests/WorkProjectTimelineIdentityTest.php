<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$identity = $read(
    'public_html/app/Services/UserIdentityLabelService.php'
);
$repository = $read(
    'public_html/app/Repositories/WorkProjectTimelineRepository.php'
);
$service = $read(
    'public_html/app/Services/Work/WorkProjectService.php'
);
$view = $read(
    'public_html/resources/views/admin/work-project-show.php'
);

$fullNamePosition = strpos($identity, "'full_name'");
$emailPosition = strpos($identity, "'email'");
$mobilePosition = strpos($identity, "'mobile'");

$expect(
    $fullNamePosition !== false
    && $emailPosition !== false
    && $mobilePosition !== false
    && $fullNamePosition < $emailPosition
    && $emailPosition < $mobilePosition,
    'Identity priority must be full name, email, then mobile.'
);

$expect(
    str_contains($identity, "\$fullName . ' — ' . \$contact"),
    'Dropdown identity labels must include a contact discriminator.'
);

$expect(
    str_contains($repository, 'work_activity_events')
    && str_contains($repository, 'LEFT JOIN work_items')
    && str_contains($repository, 'ORDER BY activity.occurred_at DESC'),
    'Project timeline repository query is incomplete.'
);

$expect(
    str_contains($service, "project['timeline']")
    && str_contains($service, 'timelineEventTitle')
    && str_contains($service, 'labelsForReferences'),
    'Project timeline service decoration is incomplete.'
);

$expect(
    str_contains($view, 'تایم‌لاین پروژه')
    && str_contains($view, "can_view_audit")
    && str_contains($view, 'work-project-timeline__entry'),
    'Timeline view or audit access guard is missing.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i',
        $repository
    ),
    'Timeline repository contains destructive SQL.'
);

echo "Work project timeline and identity priority checks passed.\n";
