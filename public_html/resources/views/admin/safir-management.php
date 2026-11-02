<?php
/** Dedicated Safir section: Core navigation, existing admin shell. */
$page = is_array($page ?? null) ? $page : [];
$status = (string) ($status ?? '');
ob_start();
require BASE_PATH . '/resources/views/admin/partials/communication-style.php';
require BASE_PATH . '/resources/views/admin/partials/bale-safir-campaign.php';
$content = (string) ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
