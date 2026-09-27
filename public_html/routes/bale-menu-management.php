<?php
declare(strict_types=1);
/* Privileged management UI reuses the existing system help-texts management
 * permission: viewing the page is NOT sufficient to publish menus.
 * Standard middleware CSRF applies; explicit form verification adds defense.
 */
$baleMenuAdminGet = function($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings/bale-menus');
    if (!is_array($context)) return $context;
    if (!(new \App\Services\AuthorizationService())->hasPermission(
        (int)$context['user_id'], 'admin.ui_content.manage')) {
        return $response->redirect('/admin/dashboard?error=forbidden');
    }
    $bmPageTitle = \App\Services\UiContent\UiContentInlineGuide::bodyText(
        'core.bale-menu.ui.page_title', 'core', 'bale-menu-admin'
    );
    try {
        $bot = trim((string)$request->input('bot', 'service-bot'));
        $screen = trim((string)$request->input('screen', ''));
        $page = (new \App\Services\BaleMenuManagementService())->page($bot, $screen);
    } catch (\Throwable $e) {
        return $adminRender($response, 'placeholder', [
            'title'=>$bmPageTitle, 'context'=>$context,
            'message'=>\App\Services\UiContent\UiContentInlineGuide::errorBodyText('core.bale-menu.error.catalog_read','core','bale-menu-admin')
        ], 503);
    }
    return $adminRender($response, 'bale-menu-management', [
        'title'=>$bmPageTitle, 'context'=>$context, 'page'=>$page,
        'status'=>trim((string)$request->input('status', ''))
    ]);
};
$baleMenuAdminPost = function($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings/bale-menus/publish');
    if (!is_array($context)) return $context;
    if (!(new \App\Services\AuthorizationService())->hasPermission(
        (int)$context['user_id'], 'admin.ui_content.manage')) {
        return $response->redirect('/admin/dashboard?error=forbidden');
    }
    $bot = trim((string)$request->input('bot', 'service-bot'));
    $screen = trim((string)$request->input('screen_key', ''));
    $url = '/admin/communications/settings/bale-menus?bot=' . rawurlencode($bot) . '&screen=' . rawurlencode($screen);
    if (!(new \IPKF\Security\Csrf())->check((string)$request->input('_token', ''))) {
        return $response->redirect($url . '&status=invalid_csrf');
    }
    try {
        $outcome = (new \App\Services\BaleMenuManagementService())->publish(
            $bot, $request->all(), (int)$context['user_id']);
        return $response->redirect($url . '&status=' . rawurlencode($outcome));
    } catch (\Throwable $e) {
        $allowed = ['MENU_STALE_FORM_REFRESH','MENU_BUTTON_FIELD_INVALID',
            'MENU_HISTORICAL_LABEL_CONFLICT','MENU_BUTTON_IDENTITY_CHANGED',
            'MENU_MODE_INVALID','MENU_COLUMNS_INVALID','MENU_TOO_MANY_ROWS',
            'MENU_TEXT_INVALID','MENU_PUBLISH_BUSY'];
        $reason = in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'save_failed';
        return $response->redirect($url . '&status=' . rawurlencode($reason));
    }
};

$router->get('/admin/communications/settings/bale-menus', $baleMenuAdminGet);
$router->post('/admin/communications/settings/bale-menus/publish', $baleMenuAdminPost);
/* Old bookmarked GET URL: redirect rather than leaving a second settings UI. */
$router->get('/admin/system/bale-menus', function($request, $response) {
    $query = [];
    foreach (['bot', 'screen'] as $key) {
        $value = trim((string)$request->input($key, ''));
        if ($value !== '' && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $value) === 1) {
            $query[$key] = $value;
        }
    }
    return $response->redirect('/admin/communications/settings/bale-menus'
        . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''));
});
/* Allow already-open legacy forms to finish under the SAME guarded handler. */
$router->post('/admin/system/bale-menus/publish', $baleMenuAdminPost);

/* B7_A130_MULTI_BOT_ADMIN — only connects an independently provisioned DEV runtime. */
$router->post('/admin/communications/settings/bale-menus/register',
    function($request, $response) use ($adminGuard) {
        $context=$adminGuard($response,'/admin/communications/settings/bale-menus/register');
        if (!is_array($context)) return $context;
        if (!(new \App\Services\AuthorizationService())->hasPermission(
            (int)$context['user_id'],'admin.ui_content.manage')) {
            return $response->redirect('/admin/dashboard?error=forbidden');
        }
        $base='/admin/communications/settings/bale-menus?tab=bots';
        if (!(new \IPKF\Security\Csrf())->check((string)$request->input('_token',''))) {
            return $response->redirect($base.'&status=invalid_csrf');
        }
        try {
            $key=trim((string)$request->input('bot_key',''));
            $site=trim((string)$request->input('site_slug',''));
            $status=(new \App\Services\BaleMenuManagementService())->registerProvisionedBot(
                $key,$site,(int)$context['user_id']);
            return $response->redirect($base.'&bot='.rawurlencode($key).'&status='.rawurlencode($status));
        } catch (\Throwable $error) {
            $allowed=['BOT_REGISTRATION_INVALID','BOT_KEY_EXISTS','BOT_REGISTRY_LIMIT',
                'BOT_NOT_PROVISIONED','BOT_ALREADY_REGISTERED','BOT_IDENTITY_CONFLICT',
                'BOT_CATALOG_NOT_READY','BOT_NOT_DEV','BOT_REGISTRATION_BUSY','BOT_REGISTRY_CHANGED'];
            $reason=in_array($error->getMessage(),$allowed,true)?$error->getMessage():'save_failed';
            return $response->redirect($base.'&status='.rawurlencode($reason));
        }
    }
);
