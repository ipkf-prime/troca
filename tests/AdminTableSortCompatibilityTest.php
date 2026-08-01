<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root
    . '/public_html/app/Support/'
    . 'AdminTableSort.php';

use App\Support\AdminTableSort;

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$new = AdminTableSort::resolve(
    'title',
    'desc',
    [
        'title' => 'items.title',
        'created_at' => 'items.created_at',
    ],
    'created_at',
    'asc'
);

$expect(
    $new['sort'] === 'title'
    && $new['dir'] === 'desc'
    && $new['sql'] === 'items.title',
    'Current resolve contract failed.'
);

$old = AdminTableSort::resolve(
    [
        'sort' => 'project',
        'dir' => 'asc',
    ],
    [
        'title' => 'items.title',
        'project' => 'projects.title',
    ],
    'title',
    'desc'
);

$expect(
    $old['column'] === 'project'
    && $old['direction'] === 'asc'
    && $old['sql'] === 'projects.title',
    'Previous resolve contract failed.'
);

$oldUrl = AdminTableSort::url(
    '/admin/work',
    [
        'scope' => 'open',
        'q' => 'test',
    ],
    'title',
    'due_at',
    'asc',
    'my-work'
);

$expect(
    $oldUrl ===
        '/admin/work?scope=open&q=test'
        . '&sort=title&dir=asc#my-work',
    'Previous URL contract failed.'
);

$newUrl = AdminTableSort::url(
    '/admin/work/projects',
    'title',
    'updated_at',
    'desc',
    [
        'status' => 'current',
    ],
    'asc'
);

$expect(
    $newUrl ===
        '/admin/work/projects?status=current'
        . '&sort=title&dir=asc',
    'Current URL contract failed.'
);

$expect(
    AdminTableSort::ariaSort(
        'title',
        'title',
        'desc'
    ) === 'descending',
    'ariaSort active state failed.'
);

$expect(
    AdminTableSort::ariaSort(
        'project',
        'title',
        'desc'
    ) === 'none',
    'ariaSort inactive state failed.'
);

echo "AdminTableSort compatibility checks passed.\n";
