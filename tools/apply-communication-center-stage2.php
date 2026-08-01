<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        throw new RuntimeException(
            "Required file missing: {$relative}"
        );
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException(
            "Unable to read: {$relative}"
        );
    }

    return $content;
};

$write = static function (
    string $relative,
    string $content
) use ($root): void {
    $path = $root . '/' . $relative;

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException(
            "Unable to write: {$relative}"
        );
    }
};

$replaceOnce = static function (
    string $content,
    string $search,
    string $replacement,
    string $label
): string {
    if (str_contains($content, $replacement)) {
        return $content;
    }

    if (!str_contains($content, $search)) {
        throw new RuntimeException(
            "Patch anchor missing: {$label}"
        );
    }

    $position = strpos($content, $search);

    if ($position === false) {
        throw new RuntimeException(
            "Patch failed: {$label}"
        );
    }

    return substr_replace(
        $content,
        $replacement,
        $position,
        strlen($search)
    );
};

$files = [];

// Migration registry
$relative =
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "                    \\IPKF\\Database\\Migrations\\CreateNotificationCoreFoundationTables::class,\n",
    "                    \\IPKF\\Database\\Migrations\\CreateNotificationCoreFoundationTables::class,\n"
    . "                    \\IPKF\\Database\\Migrations\\CreateCommunicationCenterFoundationTables::class,\n",
    'application migration registry'
);
$write($relative, $content);
$files[] = $relative;

// Main migrate endpoint
$relative = 'public_html/public/migrate.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "        new \\IPKF\\Database\\Migrations\\CreateNotificationCoreFoundationTables(),\n",
    "        new \\IPKF\\Database\\Migrations\\CreateNotificationCoreFoundationTables(),\n"
    . "        new \\IPKF\\Database\\Migrations\\CreateCommunicationCenterFoundationTables(),\n",
    'migrate list'
);
if (!str_contains(
    $content,
    'communication_center_foundation'
)) {
    $content = str_replace(
        'notification_core_foundation";',
        'notification_core_foundation, communication_center_foundation";',
        $content
    );
}
$write($relative, $content);
$files[] = $relative;

// Seeder registry
$relative =
    'public_html/system/Database/Application/'
    . 'ApplicationSeederRegistry.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "                    \\IPKF\\Database\\Seeds\\NotificationCoreSeeder::class,\n",
    "                    \\IPKF\\Database\\Seeds\\NotificationCoreSeeder::class,\n"
    . "                    \\IPKF\\Database\\Seeds\\CommunicationCenterSeeder::class,\n",
    'application seeder registry'
);
$write($relative, $content);
$files[] = $relative;

// Main seed endpoint
$relative = 'public_html/public/seed.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "        new \\IPKF\\Database\\Seeds\\NotificationCoreSeeder(),\n",
    "        new \\IPKF\\Database\\Seeds\\NotificationCoreSeeder(),\n"
    . "        new \\IPKF\\Database\\Seeds\\CommunicationCenterSeeder(),\n",
    'seed list'
);
if (!str_contains(
    $content,
    'communication_center_metadata'
)) {
    $content = str_replace(
        'notification_core_metadata";',
        'notification_core_metadata, communication_center_metadata";',
        $content
    );
}
$write($relative, $content);
$files[] = $relative;

// Route loader
$relative =
    'public_html/system/Routing/RouteLoader.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "            BASE_PATH . '/routes/notifications.php',\n",
    "            BASE_PATH . '/routes/notifications.php',\n"
    . "            BASE_PATH . '/routes/communication-center.php',\n",
    'route loader'
);
$write($relative, $content);
$files[] = $relative;

// Login unread-message notifier
$relative = 'public_html/app/Services/AuthService.php';
$content = $read($relative);
$recordBlock = <<<'PHP'
        (new LoginHistoryService())->record(
            $userId,
            $activeAssignment,
            $method,
            $mfaVerified
        );
PHP;
$replacement = $recordBlock . <<<'PHP'


        (new InternalMessageLoginNotifierService())
            ->notify($userId);
PHP;
$content = $replaceOnce(
    $content,
    $recordBlock,
    $replacement,
    'auth login notifier'
);
if (!str_contains(
    $content,
    "Session::forget('messages_unread_on_login');"
)) {
    $content = str_replace(
        "        Session::forget('module_sso_return_path');\n",
        "        Session::forget('module_sso_return_path');\n"
        . "        Session::forget('messages_unread_on_login');\n",
        $content
    );
}
$write($relative, $content);
$files[] = $relative;

// Dynamic sidebar navigation
$relative =
    'public_html/resources/views/admin/layout.php';
$content = $read($relative);
$content = $replaceOnce(
    $content,
    "\$systemNav = \$context['navigation']['system'] ?? [];\n",
    "\$navigationShell = \$isModuleShell ? \$moduleShellKey : 'core';\n"
    . "\$dynamicNavigation = new \\App\\Services\\DynamicAdminNavigationService();\n"
    . "\$systemNav = \$themeUserId !== null\n"
    . "    ? \$dynamicNavigation->navigation((int) \$themeUserId, \$navigationShell)\n"
    . "    : [];\n"
    . "\$topbarNav = \$themeUserId !== null\n"
    . "    ? \$dynamicNavigation->topbar((int) \$themeUserId, \$navigationShell)\n"
    . "    : [];\n",
    'dynamic sidebar'
);

$childAnchor = "                    </a>\n                <?php endforeach; ?>\n            </nav>";
$childReplacement = <<<'PHP'
                    </a>
                    <?php if (($item['children'] ?? []) !== []): ?>
                        <div class="admin-nav__children">
                            <?php foreach ($item['children'] as $child): ?>
                                <a
                                    class="<?= admin_nav_is_active($child, $currentPath) ? 'is-active' : '' ?>"
                                    href="<?= admin_h((string) ($child['url'] ?? '#')) ?>"
                                >
                                    <span><?= admin_h($child['title'] ?? '') ?></span>
                                    <?php if (($child['badge'] ?? '') !== ''): ?>
                                        <small class="admin-nav__badge"><?= admin_h($child['badge']) ?></small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
PHP;
$content = $replaceOnce(
    $content,
    $childAnchor,
    $childReplacement,
    'nested sidebar navigation'
);

$topbarAnchor = "                <div class=\"admin-topbar__actions\">\n";
$topbarReplacement = <<<'PHP'
                <div class="admin-topbar__actions">
                    <?php foreach ($topbarNav as $topbarItem): ?>
                        <a
                            class="admin-role admin-topbar-notification"
                            href="<?= admin_h((string) ($topbarItem['url'] ?? '#')) ?>"
                        >
                            <?= \App\Support\AdminIcon::html((string) ($topbarItem['icon'] ?? 'envelope')) ?>
                            <span><?= admin_h($topbarItem['title'] ?? '') ?></span>
                            <?php if (($topbarItem['badge'] ?? '') !== ''): ?>
                                <b><?= admin_h($topbarItem['badge']) ?></b>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
PHP;
$content = $replaceOnce(
    $content,
    $topbarAnchor,
    $topbarReplacement,
    'dynamic topbar notification'
);

if (!str_contains($content, 'communication-navigation-style')) {
    $stylePosition = strpos($content, '<style id="admin-theme-vars">');

    if ($stylePosition === false) {
        throw new RuntimeException(
            'Patch anchor missing: navigation styles'
        );
    }

    $styleEnd = strpos($content, '</style>', $stylePosition);

    if ($styleEnd === false) {
        throw new RuntimeException(
            'Patch failed: navigation styles'
        );
    }

    $styleEnd += strlen('</style>');
    $navigationStyle = <<<'HTML'

    <style id="communication-navigation-style">
        .admin-nav__children {
            display: grid;
            gap: .2rem;
            margin: .2rem 1.4rem .55rem .25rem;
        }
        .admin-nav__children a {
            font-size: .78rem;
            min-height: 2.2rem;
            padding-block: .35rem;
        }
        .admin-topbar-notification {
            align-items: center;
            display: inline-flex;
            gap: .35rem;
            text-decoration: none;
        }
        .admin-topbar-notification .admin-icon {
            height: 1rem;
            width: 1rem;
        }
    </style>
HTML;
    $content = substr_replace(
        $content,
        $navigationStyle,
        $styleEnd,
        0
    );
}

$write($relative, $content);
$files[] = $relative;

// Dynamic channel catalog in repository
$relative =
    'public_html/app/Repositories/NotificationRepository.php';
$content = $read($relative);
$channelMethod = <<<'PHP'
    public function activeChannelCodes(): array
    {
        $statement = $this->connection()->query("
            SELECT code
            FROM notification_channels
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");

        return array_values(array_map(
            'strval',
            $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []
        ));
    }

PHP;
if (!str_contains($content, 'public function activeChannelCodes')) {
    $anchor = '    private function resolveUserId(';

    if (!str_contains($content, $anchor)) {
        throw new RuntimeException(
            'Patch anchor missing: notification repository channels'
        );
    }

    $content = str_replace(
        $anchor,
        $channelMethod . $anchor,
        $content
    );
}
$write($relative, $content);
$files[] = $relative;

// Dynamic channel validation in publisher and worker
foreach ([
    'public_html/app/Services/NotificationPublisherService.php',
    'public_html/app/Services/NotificationOutboxProcessorService.php',
] as $relative) {
    $content = $read($relative);

    if (!str_contains($content, 'activeChannelCodes')) {
        if (!str_contains(
            $content,
            "        \$allowed = ['in_app', 'email', 'sms', 'bale'];\n"
        )) {
            throw new RuntimeException(
                "Patch anchor missing: {$relative} channel list"
            );
        }

        $content = str_replace(
            "        \$allowed = ['in_app', 'email', 'sms', 'bale'];\n",
            "        \$allowed = \$this->notifications\n"
            . "            ->activeChannelCodes();\n",
            $content
        );
    }

    $write($relative, $content);
    $files[] = $relative;
}

$write(
    'public_html/VERSION',
    "0.6.0-communication-center-stage2-dev\n"
);
$files[] = 'public_html/VERSION';

echo "Communication Center Stage 2 patch applied.\n";
echo "changed_files=" . count($files) . "\n";

foreach ($files as $file) {
    echo $file . "\n";
}
