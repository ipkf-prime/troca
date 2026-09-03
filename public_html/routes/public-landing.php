<?php

$landingRedirect = static function (
    $response,
    string $status,
    int $editId = 0,
    string $tab = ''
) {
    $url = '/admin/public-page?status='
        . rawurlencode($status);

    if ($tab !== '') {
        $url .= '&tab=' . rawurlencode($tab);
    }

    if ($editId > 0) {
        $url .= '&edit_id=' . $editId;
    }

    return $response->redirect($url);
};

$router->get(
    '/admin/public-page',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard
    ) {
        $context = $adminGuard(
            $response,
            '/admin/public-page'
        );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page = (
                new \App\Services\PublicLandingService()
            )->adminPage(
                max(
                    0,
                    (int) $request->input(
                        'edit_id',
                        0
                    )
                )
            );
        } catch (\Throwable) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' => 'مدیریت صفحه عمومی',
                    'context' => $context,
                    'message' =>
                        'ابتدا Migration صفحه عمومی را اجرا کنید.',
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'public-page',
            [
                'title' => 'مدیریت صفحه عمومی',
                'context' => $context,
                'page' => $page,
                'status' => trim(
                    (string) $request->input(
                        'status',
                        ''
                    )
                ),
            ]
        );
    }
);

$router->post(
    '/admin/public-page/settings',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $landingRedirect
    ) {
        $context = $adminGuard(
            $response,
            '/admin/public-page'
        );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $landingRedirect(
                $response,
                'invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\PublicLandingService()
            )->saveSettings(
                $request->all(),
                (int) $context['user_id']
            ,
                $_FILES['brand_logo'] ?? null
            );

            return $landingRedirect(
                $response,
                'settings_saved',
                0,
                'settings'
            );
        } catch (\Throwable $exception) {
            return $landingRedirect(
                $response,
                $exception->getMessage()
            );
        }
    }
);

$router->post(
    '/admin/public-page/items',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $landingRedirect
    ) {
        $context = $adminGuard(
            $response,
            '/admin/public-page'
        );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $landingRedirect(
                $response,
                'invalid_csrf'
            );
        }

        $id = max(
            0,
            (int) $request->input('id', 0)
        );

        try {
            $savedId = (
                new \App\Services\PublicLandingService()
            )->saveItem(
                $request->all(),
                [
                    'image' =>
                        $_FILES['image'] ?? null,
                    'mobile_image' =>
                        $_FILES['mobile_image'] ?? null,
                ],
                (int) $context['user_id']
            );

            return $landingRedirect(
                $response,
                'item_saved',
                $savedId,
                trim(
                    (string) $request->input(
                        '_tab',
                        ''
                    )
                )
            );
        } catch (\Throwable $exception) {
            return $landingRedirect(
                $response,
                $exception->getMessage(),
                $id,
                trim(
                    (string) $request->input(
                        '_tab',
                        ''
                    )
                )
            );
        }
    }
);

$router->post(
    '/admin/public-page/items/delete',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $landingRedirect
    ) {
        $context = $adminGuard(
            $response,
            '/admin/public-page'
        );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $landingRedirect(
                $response,
                'invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\PublicLandingService()
            )->deleteItem(
                max(
                    0,
                    (int) $request->input(
                        'id',
                        0
                    )
                ),
                (int) $context['user_id']
            );

            return $landingRedirect(
                $response,
                'item_deleted',
                0,
                trim(
                    (string) $request->input(
                        '_tab',
                        ''
                    )
                )
            );
        } catch (\Throwable $exception) {
            return $landingRedirect(
                $response,
                $exception->getMessage()
            );
        }
    }
);
