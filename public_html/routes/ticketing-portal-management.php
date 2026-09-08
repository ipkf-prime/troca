<?php

declare(strict_types=1);


/**
 * TICKETING_PORTAL_ADMIN_ROUTES_WRITE_V1
 *
 * GET:
 *   /admin/ticketing/portals
 *
 * POST:
 *   /host
 *   /brand
 *   /settings
 *   /items
 *   /items/delete
 *
 * Contract:
 *   adminGuard -> CSRF -> Writer Service -> redirect.
 */


$portalAdminRedirect =
    static function (
        $response,
        string $portalReference,
        string $status,
        string $tab = 'overview',
        int $editId = 0
    ) {

        $url =
            '/admin/ticketing/portals'
            . '?portal='
            . rawurlencode(
                $portalReference
            )
            . '&status='
            . rawurlencode(
                $status
            )
            . '&tab='
            . rawurlencode(
                $tab
            );


        if ($editId > 0) {

            $url .=
                '&edit_id='
                . $editId;
        }


        return
            $response->redirect(
                $url
            );
    };


$portalAdminCsrf =
    static function (
        $request
    ): bool {

        return
            (
                new \IPKF\Security\Csrf()
            )->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            );
    };


$router->get(
    '/admin/ticketing/portals',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $adminRender
    ) {

        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/portals'
            );


        if (!is_array($context)) {
            return $context;
        }


        try {

            $page =
                (
                    new \App\Services\Ticketing\PortalManagementService()
                )->adminPage(
                    (int) $context[
                        'user_id'
                    ],
                    trim(
                        (string) $request->input(
                            'portal',
                            ''
                        )
                    ),
                    max(
                        0,
                        (int) $request->input(
                            'edit_id',
                            0
                        )
                    )
                );


        } catch (\Throwable) {

            return
                $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'مدیریت پورتال‌های تیکتینگ',

                        'context' =>
                            $context,

                        'message' =>
                            'اطلاعات مدیریت پورتال در حال حاضر در دسترس نیست.',
                    ],
                    503
                );
        }


        return
            $adminRender(
                $response,
                'ticketing-portal-management',
                [
                    'title' =>
                        'مدیریت پورتال‌های تیکتینگ',

                    'context' =>
                        $context,

                    'page' =>
                        $page,

                    'status' =>
                        trim(
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
    '/admin/ticketing/portals/host',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $portalAdminCsrf,
        $portalAdminRedirect
    ) {

        $path =
            '/admin/ticketing/portals/host';


        $context =
            $adminGuard(
                $response,
                $path
            );


        if (!is_array($context)) {
            return $context;
        }


        $portalReference =
            trim(
                (string) $request->input(
                    'portal_reference',
                    ''
                )
            );


        if (
            !$portalAdminCsrf(
                $request
            )
        ) {
            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'invalid_csrf',
                    'host'
                );
        }


        try {

            (
                new \App\Services\Ticketing\PortalManagementWriteService()
            )->saveHost(
                $request->all(),
                (int) $context[
                    'user_id'
                ]
            );


            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'host_saved',
                    'host'
                );


        } catch (\Throwable $exception) {

            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    $exception->getMessage(),
                    'host'
                );
        }
    }
);


$router->post(
    '/admin/ticketing/portals/brand',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $portalAdminCsrf,
        $portalAdminRedirect
    ) {

        $path =
            '/admin/ticketing/portals/brand';


        $context =
            $adminGuard(
                $response,
                $path
            );


        if (!is_array($context)) {
            return $context;
        }


        $portalReference =
            trim(
                (string) $request->input(
                    'portal_reference',
                    ''
                )
            );


        $scope =
            trim(
                (string) $request->input(
                    'brand_scope',
                    'portal'
                )
            );


        $tab =
            $scope === 'project'
                ? 'project-brand'
                : 'portal-brand';


        if (
            !$portalAdminCsrf(
                $request
            )
        ) {
            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'invalid_csrf',
                    $tab
                );
        }


        try {

            (
                new \App\Services\Ticketing\PortalManagementWriteService()
            )->saveBrand(
                $request->all(),
                (int) $context[
                    'user_id'
                ],
                $_FILES[
                    'logo'
                ]
                ?? null
            );


            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'brand_saved',
                    $tab
                );


        } catch (\Throwable $exception) {

            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    $exception->getMessage(),
                    $tab
                );
        }
    }
);


$router->post(
    '/admin/ticketing/portals/settings',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $portalAdminCsrf,
        $portalAdminRedirect
    ) {

        $path =
            '/admin/ticketing/portals/settings';


        $context =
            $adminGuard(
                $response,
                $path
            );


        if (!is_array($context)) {
            return $context;
        }


        $portalReference =
            trim(
                (string) $request->input(
                    'portal_reference',
                    ''
                )
            );


        if (
            !$portalAdminCsrf(
                $request
            )
        ) {
            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'invalid_csrf',
                    'settings'
                );
        }


        try {

            (
                new \App\Services\Ticketing\PortalManagementWriteService()
            )->saveLandingSettings(
                $request->all(),
                (int) $context[
                    'user_id'
                ]
            );


            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'settings_saved',
                    'settings'
                );


        } catch (\Throwable $exception) {

            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    $exception->getMessage(),
                    'settings'
                );
        }
    }
);


$router->post(
    '/admin/ticketing/portals/items',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $portalAdminCsrf,
        $portalAdminRedirect
    ) {

        $path =
            '/admin/ticketing/portals/items';


        $context =
            $adminGuard(
                $response,
                $path
            );


        if (!is_array($context)) {
            return $context;
        }


        $portalReference =
            trim(
                (string) $request->input(
                    'portal_reference',
                    ''
                )
            );


        if (
            !$portalAdminCsrf(
                $request
            )
        ) {
            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'invalid_csrf',
                    'items'
                );
        }


        $id =
            max(
                0,
                (int) $request->input(
                    'id',
                    0
                )
            );


        try {

            $savedId =
                (
                    new \App\Services\Ticketing\PortalManagementWriteService()
                )->saveItem(
                    $request->all(),
                    (int) $context[
                        'user_id'
                    ],
                    $_FILES[
                        'image'
                    ]
                    ?? null,
                    $_FILES[
                        'mobile_image'
                    ]
                    ?? null
                );


            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'item_saved',
                    'items',
                    $savedId
                );


        } catch (\Throwable $exception) {

            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    $exception->getMessage(),
                    'items',
                    $id
                );
        }
    }
);


$router->post(
    '/admin/ticketing/portals/items/delete',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $portalAdminCsrf,
        $portalAdminRedirect
    ) {

        $path =
            '/admin/ticketing/portals/items/delete';


        $context =
            $adminGuard(
                $response,
                $path
            );


        if (!is_array($context)) {
            return $context;
        }


        $portalReference =
            trim(
                (string) $request->input(
                    'portal_reference',
                    ''
                )
            );


        if (
            !$portalAdminCsrf(
                $request
            )
        ) {
            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'invalid_csrf',
                    'items'
                );
        }


        try {

            (
                new \App\Services\Ticketing\PortalManagementWriteService()
            )->deleteItem(
                $request->all(),
                (int) $context[
                    'user_id'
                ]
            );


            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    'item_deleted',
                    'items'
                );


        } catch (\Throwable $exception) {

            return
                $portalAdminRedirect(
                    $response,
                    $portalReference,
                    $exception->getMessage(),
                    'items'
                );
        }
    }
);
