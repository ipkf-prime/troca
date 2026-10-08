<?php

$adminUserManagementForbidden = static function (
    $response,
    callable $adminRender,
    array $context
) {
    return $adminRender($response, 'forbidden', [
        'title' => 'دسترسی غیرمجاز',
        'context' => $context,
    ], 403);
};

$adminUserVerificationRedirect = static function (
    int $userId,
    array $verification,
    string $status
): string {
    $parts = [];

    foreach ($verification as $field => $result) {
        $state = (string) (
            $result['status'] ?? 'delivery_failed'
        );
        $parts[] = $field . ':' . $state;

        if (
            isset($result['dev_token'])
            && $result['dev_token'] !== null
        ) {
            $_SESSION['admin_identity_dev_otp'][
                $userId
            ][$field] = (string) $result['dev_token'];
        }
    }

    $query = ['status' => $status];

    if ($parts !== []) {
        $query['verification'] = implode(',', $parts);
    }

    return '/admin/users/'
        . $userId
        . '/edit?'
        . http_build_query($query);
};

$adminImpersonationPresentation =
    static function (): array {
        $service =
            new \App\Services\ImpersonationUiService();

        $content =
            $service->resolve();

        $state =
            (
                new \App\Services\ImpersonationSessionLifecycleService()
            )->status();

        return [
            'active' =>
                !empty(
                    $state[
                        'valid'
                    ]
                )
                && !empty(
                    $state[
                        'active'
                    ]
                ),

            'ready' =>
                $service->ready(
                    $content
                ),

            'action_label' =>
                $service->text(
                    $content,
                    'action'
                ),

            'confirm_title' =>
                $service->text(
                    $content,
                    'confirm_title'
                ),

            'confirm_body' =>
                $service->text(
                    $content,
                    'confirm_body'
                ),

            'mode_label' =>
                $service->text(
                    $content,
                    'mode_label'
                ),

            'mode_observe' =>
                $service->text(
                    $content,
                    'mode_observe'
                ),

            'mode_operate' =>
                $service->text(
                    $content,
                    'mode_operate'
                ),

            'confirm_operate' =>
                $service->text(
                    $content,
                    'confirm_operate'
                ),
        ];
    };


$adminImpersonationActionFor =
    static function (
        int $actorUserId,
        int $actorAssignmentId,
        int $targetUserId,
        array $presentation,
        string $csrfToken,
        string $returnPath
    ): ?array {
        if (
            $actorUserId < 1
            || $actorAssignmentId < 1
            || $targetUserId < 1
            || !empty(
                $presentation[
                    'active'
                ]
            )
            || empty(
                $presentation[
                    'ready'
                ]
            )
        ) {
            return null;
        }

        try {
            $decision =
                (
                    new \App\Services\ImpersonationAuthorizationService()
                )->decide(
                    $actorUserId,
                    $targetUserId,
                    $actorAssignmentId
                );
        } catch (\Throwable) {
            return null;
        }

        if (
            !is_array($decision)
            || (
                $decision[
                    'allowed'
                ]
                ?? false
            ) !== true
        ) {
            return null;
        }

        $canOperate =
            false;

        try {
            $authorizationRepository =
                new \App\Repositories\ImpersonationAuthorizationRepository();

            $canOperate =
                $authorizationRepository->actorAssignment(
                    $actorUserId,
                    $actorAssignmentId
                ) !== null
                &&
                $authorizationRepository->permissionForAssignment(
                    $actorUserId,
                    $actorAssignmentId,
                    'users.impersonate.operate'
                );

        } catch (\Throwable) {
            $canOperate =
                false;
        }

        $modeOptions = [
            [
                'value' =>
                    \App\Services\ImpersonationContextService::MODE_OBSERVE,

                'label' =>
                    (string) (
                        $presentation[
                            'mode_observe'
                        ]
                        ?? ''
                    ),

                'confirm_message' =>
                    (string) (
                        $presentation[
                            'confirm_body'
                        ]
                        ?? ''
                    ),
            ],
        ];

        if ($canOperate) {
            $modeOptions[] = [
                'value' =>
                    \App\Services\ImpersonationContextService::MODE_OPERATE,

                'label' =>
                    (string) (
                        $presentation[
                            'mode_operate'
                        ]
                        ?? ''
                    ),

                'confirm_message' =>
                    (string) (
                        $presentation[
                            'confirm_operate'
                        ]
                        ?? ''
                    ),
            ];
        }

        $label =
            trim(
                (string) (
                    $presentation[
                        'action_label'
                    ]
                    ?? ''
                )
            );

        $confirmBody =
            trim(
                (string) (
                    $presentation[
                        'confirm_body'
                    ]
                    ?? ''
                )
            );

        if (
            $label === ''
            || $confirmBody === ''
        ) {
            return null;
        }

        return [
            'method' =>
                'POST',

            'url' =>
                '/admin/users/'
                . $targetUserId
                . '/impersonate',

            'label' =>
                $label,

            'mode_label' =>
                (string) (
                    $presentation[
                        'mode_label'
                    ]
                    ?? ''
                ),

            'mode_options' =>
                $modeOptions,

            'confirm_title' =>
                (string) (
                    $presentation[
                        'confirm_title'
                    ]
                    ?? ''
                ),

            'confirm_message' =>
                $confirmBody,

            'fields' => [
                '_token' =>
                    $csrfToken,

                'return_path' =>
                    $returnPath,
            ],
        ];
    };


$adminImpersonationOperateGrantFor =
    static function (
        int $actorUserId,
        int $actorAssignmentId,
        int $targetUserId,
        string $csrfToken,
        string $feedbackStatus
    ): ?array {
        try {
            $service =
                new \App\Services\ImpersonationOperateGrantService();

            $state =
                $service->state(
                    $actorUserId,
                    $actorAssignmentId,
                    $targetUserId
                );

            if (
                (
                    $state['visible']
                    ?? false
                ) !== true
            ) {
                return null;
            }

            $ui =
                new \App\Services\ImpersonationOperateGrantUiService();

            $content =
                $ui->resolve();

            if (!$ui->ready($content)) {
                return null;
            }

            $effect =
                (string) (
                    $state['effect']
                    ?? ''
                );

            $baseCapabilityActive =
                (
                    $state[
                        'base_capability_active'
                    ]
                    ?? false
                ) === true;

            $statusName =
                $effect === 'allow'
                    ? (
                        $baseCapabilityActive
                            ? 'status_allowed'
                            : 'status_ineligible'
                    )
                    : 'status_denied';

            $action = null;

            if (
                (
                    $state['can_revoke']
                    ?? false
                ) === true
            ) {
                $action = [
                    'method' =>
                        'POST',

                    'url' =>
                        '/admin/users/'
                        . $targetUserId
                        . '/impersonation-operate-access',

                    'label' =>
                        $ui->text(
                            $content,
                            'revoke'
                        ),

                    'confirm_title' =>
                        $ui->text(
                            $content,
                            'confirm_revoke_title'
                        ),

                    'confirm_message' =>
                        $ui->text(
                            $content,
                            'confirm_revoke'
                        ),

                    'fields' => [
                        '_token' =>
                            $csrfToken,

                        'effect' =>
                            'deny',
                    ],

                    'reason_label' =>
                        $ui->text(
                            $content,
                            'reason_label'
                        ),

                    'reason_placeholder' =>
                        $ui->text(
                            $content,
                            'reason_placeholder'
                        ),
                ];

            } elseif (
                (
                    $state['can_grant']
                    ?? false
                ) === true
            ) {
                $action = [
                    'method' =>
                        'POST',

                    'url' =>
                        '/admin/users/'
                        . $targetUserId
                        . '/impersonation-operate-access',

                    'label' =>
                        $ui->text(
                            $content,
                            'grant'
                        ),

                    'confirm_title' =>
                        $ui->text(
                            $content,
                            'confirm_grant_title'
                        ),

                    'confirm_message' =>
                        $ui->text(
                            $content,
                            'confirm_grant'
                        ),

                    'fields' => [
                        '_token' =>
                            $csrfToken,

                        'effect' =>
                            'allow',
                    ],

                    'reason_label' =>
                        $ui->text(
                            $content,
                            'reason_label'
                        ),

                    'reason_placeholder' =>
                        $ui->text(
                            $content,
                            'reason_placeholder'
                        ),
                ];
            }

            $feedbackName =
                match (
                    strtolower(
                        trim(
                            $feedbackStatus
                        )
                    )
                ) {
                    'updated' =>
                        'feedback_updated',

                    'denied' =>
                        'feedback_denied',

                    default =>
                        '',
                };

            $feedback =
                $feedbackName !== ''
                    ? $ui->text(
                        $content,
                        $feedbackName
                    )
                    : '';

            return [
                'visible' =>
                    true,

                'title' =>
                    $ui->text(
                        $content,
                        'title'
                    ),

                'description' =>
                    $ui->text(
                        $content,
                        'description'
                    ),

                'status_label' =>
                    $ui->text(
                        $content,
                        $statusName
                    ),

                'status_code' =>
                    $effect === 'allow'
                        ? (
                            $baseCapabilityActive
                                ? 'active'
                                : 'warning'
                        )
                        : 'inactive',

                'feedback' =>
                    $feedback,

                'feedback_code' =>
                    $feedbackName ===
                        'feedback_updated'
                            ? 'active'
                            : 'warning',

                'action' =>
                    $action,
            ];

        } catch (\Throwable) {
            return null;
        }
    };


$adminImpersonationSafeReturn =
    static function (
        mixed $candidate,
        string $fallback = '/admin/users'
    ): string {
        $candidate =
            trim(
                (string) $candidate
            );

        if (
            $candidate === ''
            || !str_starts_with(
                $candidate,
                '/'
            )
            || str_starts_with(
                $candidate,
                '//'
            )
            || preg_match(
                '/[\r\n]/',
                $candidate
            ) === 1
        ) {
            return $fallback;
        }

        $parts =
            parse_url(
                $candidate
            );

        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return $fallback;
        }

        return $candidate;
    };


$adminImpersonationStatusUrl =
    static function (
        string $path,
        string $status
    ): string {
        $separator =
            str_contains(
                $path,
                '?'
            )
                ? '&'
                : '?';

        return
            $path
            . $separator
            . http_build_query([
                'impersonation_status' =>
                    $status,
            ]);
    };

$router->get('/admin/users', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminImpersonationPresentation,
    $adminImpersonationActionFor
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $management = new \App\Services\AdminUserManagementService();
    $list = (new \App\Services\AdminUserService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    $presentation =
        $adminImpersonationPresentation();

    $snapshot =
        (
            new \App\Services\AuthService()
        )->impersonationAuthSnapshot();

    $actorAssignmentId =
        (int) (
            $snapshot[
                'active_role_assignment_id'
            ]
            ?? 0
        );

    $csrfToken =
        (
            new \IPKF\Security\Csrf()
        )->token();

    $returnPath =
        (string) (
            $_SERVER[
                'REQUEST_URI'
            ]
            ?? '/admin/users'
        );

    if (
        is_array(
            $list[
                'items'
            ]
            ?? null
        )
    ) {
        foreach (
            $list['items']
            as &$listedUser
        ) {
            $listedUser[
                'impersonation_action'
            ] =
                $adminImpersonationActionFor(
                    (int) $context['user_id'],
                    $actorAssignmentId,
                    (int) (
                        $listedUser[
                            'id'
                        ]
                        ?? 0
                    ),
                    $presentation,
                    $csrfToken,
                    $returnPath
                );
        }

        unset($listedUser);
    }

    return $adminRender($response, 'users', [
        'title' => 'کاربران',
        'context' => $context,
        'list' => $list,
        'canCreate' => $management->canCreate(
            (int) $context['user_id']
        ),
        'canUpdate' => $management->canUpdate(
            (int) $context['user_id']
        ),
        'status' => trim(
            (string) $request->input('status', '')
        ),
    ]);
});

$router->get('/admin/users/create', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canCreate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form((int) $context['user_id']);

    return $adminRender($response, 'admin-user-form', [
        'title' => 'افزودن دستی کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => [],
        'status' => '',
    ]);
});

$router->post('/admin/users', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canCreate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $result = $service->create(
        (int) $context['user_id'],
        $request->all()
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            $adminUserVerificationRedirect(
                (int) $result['user_id'],
                $result['verification'] ?? [],
                'created'
            )
        );
    }

    if (($result['forbidden'] ?? false) === true) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form(
        (int) $context['user_id'],
        null,
        $result['form'] ?? $request->all()
    );

    return $adminRender($response, 'admin-user-form', [
        'title' => 'افزودن دستی کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? [
            'invalid' => 'اطلاعات واردشده معتبر نیست.',
        ],
        'status' => '',
    ], 422);
});

$router->get('/admin/users/invite', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $service =
        new \App\Services\UserInvitationService();

    if (!$service->canInvite(
        (int) $context['user_id']
    )) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $createdInvitation =
        \IPKF\Support\Session::get(
            'admin_user_invitation_created'
        );

    \IPKF\Support\Session::forget(
        'admin_user_invitation_created'
    );

    return $adminRender(
        $response,
        'user-invite',
        [
            'title' =>
                'دعوت کاربر',
            'context' =>
                $context,
            'errors' => [],
            'old' => [
                'full_name' => '',
                'mobile' => '',
                'email' => '',
                'expires_days' => 7,
            ],
            'createdInvitation' =>
                is_array(
                    $createdInvitation
                )
                    ? $createdInvitation
                    : null,
        ]
    );
});


$router->post('/admin/users/invite', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $service =
        new \App\Services\UserInvitationService();

    if (!$service->canInvite(
        (int) $context['user_id']
    )) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    if (
        !(new \IPKF\Security\Csrf())
            ->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
    ) {
        return $adminRender(
            $response,
            'user-invite',
            [
                'title' =>
                    'دعوت کاربر',
                'context' =>
                    $context,
                'errors' => [
                    'general' =>
                        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',
                ],
                'old' =>
                    $request->all(),
                'createdInvitation' =>
                    null,
            ],
            422
        );
    }

    $input =
        $request->all();

    $input['created_ip'] =
        $_SERVER['REMOTE_ADDR']
        ?? '';

    $input['created_user_agent'] =
        $_SERVER['HTTP_USER_AGENT']
        ?? '';

    $result =
        $service->create(
            (int) $context['user_id'],
            $input
        );

    if (($result['ok'] ?? false)
        === true
    ) {
        \IPKF\Support\Session::put(
            'admin_user_invitation_created',
            $result['invitation']
        );

        return $response->redirect(
            '/admin/users/invite'
            . '?status=created'
        );
    }

    if (($result['forbidden'] ?? false)
        === true
    ) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    return $adminRender(
        $response,
        'user-invite',
        [
            'title' =>
                'دعوت کاربر',
            'context' =>
                $context,
            'errors' =>
                $result['errors']
                ?? [
                    'general' =>
                        'ایجاد دعوت انجام نشد.',
                ],
            'old' =>
                $result['form']
                ?? $request->all(),
            'createdInvitation' =>
                null,
        ],
        422
    );
});


$router->get('/admin/users/{id}/edit', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canUpdate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $userId = filter_var(
        $request->route('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    $page = $service->form(
        (int) $context['user_id'],
        (int) $userId
    );

    if (($page['ok'] ?? false) !== true) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    return $adminRender($response, 'admin-user-form', [
        'title' => 'ویرایش کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => [],
        'status' => trim(
            (string) $request->input('status', '')
        ),
    ]);
});

$router->post('/admin/users/{id}', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canUpdate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $userId = filter_var(
        $request->route('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    $result = $service->update(
        (int) $context['user_id'],
        (int) $userId,
        $request->all()
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            $adminUserVerificationRedirect(
                (int) $userId,
                $result['verification'] ?? [],
                'saved'
            )
        );
    }

    if (($result['not_found'] ?? false) === true) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    if (($result['forbidden'] ?? false) === true) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form(
        (int) $context['user_id'],
        (int) $userId,
        $result['form'] ?? $request->all()
    );

    return $adminRender($response, 'admin-user-form', [
        'title' => 'ویرایش کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? [
            'invalid' => 'اطلاعات واردشده معتبر نیست.',
        ],
        'status' => '',
    ], 422);
});



$router->post(
    '/admin/users/{id}/impersonate',
    function (
        $request,
        $response
    ) use (
        $adminImpersonationPresentation,
        $adminImpersonationSafeReturn,
        $adminImpersonationStatusUrl
    ) {
        $returnPath =
            $adminImpersonationSafeReturn(
                $request->input(
                    'return_path',
                    '/admin/users'
                )
            );

        if (
            !(new \IPKF\Security\Csrf())
                ->check(
                    (string) $request->input(
                        '_token',
                        ''
                    )
                )
        ) {
            return
                $response->redirect(
                    $adminImpersonationStatusUrl(
                        $returnPath,
                        'denied'
                    )
                );
        }

        $targetUserId =
            filter_var(
                $request->route(
                    'id'
                ),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' =>
                            1,
                    ],
                ]
            );

        if ($targetUserId === false) {
            return
                $response->redirect(
                    $adminImpersonationStatusUrl(
                        $returnPath,
                        'denied'
                    )
                );
        }

        $presentation =
            $adminImpersonationPresentation();

        if (
            empty(
                $presentation[
                    'ready'
                ]
            )
        ) {
            return
                $response->redirect(
                    $adminImpersonationStatusUrl(
                        $returnPath,
                        'denied'
                    )
                );
        }

        try {
            $snapshot =
                (
                    new \App\Services\AuthService()
                )->impersonationAuthSnapshot();

            $actorAssignmentId =
                (int) (
                    $snapshot[
                        'active_role_assignment_id'
                    ]
                    ?? 0
                );

            $result =
                (
                    new \App\Services\ImpersonationSessionLifecycleService()
                )->start(
                    (int) $targetUserId,
                    $actorAssignmentId,
                    $returnPath,
                    (string) $request->input(
                        'mode',
                        \App\Services\ImpersonationContextService::MODE_OBSERVE
                    )
                );
        } catch (\Throwable) {
            $result = [
                'ok' =>
                    false,
            ];
        }

        if (
            (
                $result[
                    'ok'
                ]
                ?? false
            ) === true
        ) {
            /*
             * /admin resolves the Effective User's
             * canonical home instead of forcing an
             * administrator-only destination.
             */
            return
                $response->redirect(
                    '/admin'
                );
        }

        return
            $response->redirect(
                $adminImpersonationStatusUrl(
                    $returnPath,
                    'denied'
                )
            );
    }
);


$router->post(
    '/admin/impersonation/stop',
    function (
        $request,
        $response
    ) use (
        $adminImpersonationStatusUrl
    ) {
        if (
            !(new \IPKF\Security\Csrf())
                ->check(
                    (string) $request->input(
                        '_token',
                        ''
                    )
                )
        ) {
            return
                $response->redirect(
                    $adminImpersonationStatusUrl(
                        '/admin',
                        'denied'
                    )
                );
        }

        try {
            $result =
                (
                    new \App\Services\ImpersonationSessionLifecycleService()
                )->stop(
                    (string) $request->input(
                        'nonce',
                        ''
                    )
                );
        } catch (\Throwable) {
            $result = [
                'ok' =>
                    false,
            ];
        }

        if (
            (
                $result[
                    'ok'
                ]
                ?? false
            ) === true
        ) {
            $returnPath =
                trim(
                    (string) (
                        $result[
                            'return_path'
                        ]
                        ?? '/admin/users'
                    )
                );

            if (
                $returnPath === ''
                || !str_starts_with(
                    $returnPath,
                    '/'
                )
                || str_starts_with(
                    $returnPath,
                    '//'
                )
            ) {
                $returnPath =
                    '/admin/users';
            }

            return
                $response->redirect(
                    $returnPath
                );
        }

        if (
            !empty(
                $result[
                    'session_terminated'
                ]
            )
        ) {
            return
                $response->redirect(
                    '/admin/login'
                );
        }

        return
            $response->redirect(
                $adminImpersonationStatusUrl(
                    '/admin',
                    'denied'
                )
            );
    }
);

$router->post('/admin/users/{id}/roles', function (
    $request,
    $response
) use (
    $adminGuard
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $userId =
        max(
            0,
            (int) $request->route(
                'id'
            )
        );

    return $response->redirect(
        '/admin/access-control'
        . '?tab=users'
        . '&user_id='
        . $userId
        . '&status=access_management_moved'
    );
});


$router->post(
    '/admin/users/{id}/impersonation-operate-access',
    function (
        $request,
        $response
    ) use (
        $adminGuard
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/users'
            );

        if (!is_array($context)) {
            return $context;
        }

        $userId =
            filter_var(
                $request->route('id'),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        if ($userId === false) {
            return
                $response->redirect(
                    '/admin/users'
                );
        }

        $status =
            'denied';

        if (
            (new \IPKF\Security\Csrf())
                ->check(
                    (string) $request->input(
                        '_token',
                        ''
                    )
                )
        ) {
            try {
                $snapshot =
                    (
                        new \App\Services\AuthService()
                    )->impersonationAuthSnapshot();

                $actorAssignmentId =
                    (int) (
                        $snapshot[
                            'active_role_assignment_id'
                        ]
                        ?? 0
                    );

                $result =
                    (
                        new \App\Services\ImpersonationOperateGrantService()
                    )->update(
                        (int) $context['user_id'],
                        $actorAssignmentId,
                        (int) $userId,
                        (string) $request->input(
                            'effect',
                            ''
                        ),
                        (string) $request->input(
                            'reason',
                            ''
                        ),
                        (string) (
                            $_SERVER[
                                'REMOTE_ADDR'
                            ]
                            ?? ''
                        )
                    );

                if (
                    (
                        $result['ok']
                        ?? false
                    ) === true
                ) {
                    $status =
                        'updated';
                }

            } catch (\Throwable) {
                $status =
                    'denied';
            }
        }

        return
            $response->redirect(
                '/admin/users/'
                . (int) $userId
                . '/access?'
                . http_build_query([
                    'operate_access_status' =>
                        $status,
                ])
            );
    }
);


$adminManagedUserDetailRoute = function (
    string $pattern,
    string $tab
) use (
    $router,
    $adminRender,
    $adminGuard,
    $adminImpersonationPresentation,
    $adminImpersonationActionFor,
    $adminImpersonationOperateGrantFor
) {
    $router->get($pattern, function (
        $request,
        $response
    ) use (
        $tab,
        $adminRender,
        $adminGuard,
        $adminImpersonationPresentation,
        $adminImpersonationActionFor,
        $adminImpersonationOperateGrantFor
    ) {
        $context = $adminGuard($response, '/admin/users');
        if (!is_array($context)) {
            return $context;
        }

        $userId = filter_var(
            $request->route('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($userId === false) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'کاربر پیدا نشد',
                'context' => $context,
            ], 404);
        }

        $detail = (new \App\Services\AdminUserDetailCompletionService())
            ->workspace(
                (int) $userId,
                $tab,
                (int) $context['user_id']
            );

        if ($detail === null) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'کاربر پیدا نشد',
                'context' => $context,
            ], 404);
        }

        $presentation =
            $adminImpersonationPresentation();

        $snapshot =
            (
                new \App\Services\AuthService()
            )->impersonationAuthSnapshot();

        $actorAssignmentId =
            (int) (
                $snapshot[
                    'active_role_assignment_id'
                ]
                ?? 0
            );

        $action =
            $adminImpersonationActionFor(
                (int) $context['user_id'],
                $actorAssignmentId,
                (int) $userId,
                $presentation,
                (
                    new \IPKF\Security\Csrf()
                )->token(),
                (string) (
                    $_SERVER[
                        'REQUEST_URI'
                    ]
                    ?? (
                        '/admin/users/'
                        . (int) $userId
                    )
                )
            );

        if ($action !== null) {
            if (
                !isset(
                    $detail[
                        'workspace'
                    ]
                )
                || !is_array(
                    $detail[
                        'workspace'
                    ]
                )
            ) {
                $detail[
                    'workspace'
                ] = [];
            }

            if (
                !isset(
                    $detail[
                        'workspace'
                    ][
                        'actions'
                    ]
                )
                || !is_array(
                    $detail[
                        'workspace'
                    ][
                        'actions'
                    ]
                )
            ) {
                $detail[
                    'workspace'
                ][
                    'actions'
                ] = [];
            }

            $detail[
                'workspace'
            ][
                'actions'
            ][] = $action;
        }

        if ($tab === 'access') {
            $operateGrantPanel =
                $adminImpersonationOperateGrantFor(
                    (int) $context['user_id'],
                    $actorAssignmentId,
                    (int) $userId,
                    (
                        new \IPKF\Security\Csrf()
                    )->token(),
                    (string) $request->input(
                        'operate_access_status',
                        ''
                    )
                );

            if (
                is_array(
                    $operateGrantPanel
                )
            ) {
                /*
                 * Person-grant administration is page-level
                 * presentation state, not tab-domain data.
                 *
                 * Bind it directly to Detail so completion
                 * service tab payload normalization cannot
                 * discard it.
                 */
                $detail[
                    'impersonation_operate_access'
                ] =
                    $operateGrantPanel;
            }
        }

        return $adminRender($response, 'user-detail', [
            'title' => 'جزئیات کاربر',
            'context' => $context,
            'detail' => $detail,
        ]);
    });
};

$adminManagedUserDetailRoute('/admin/users/{id}', 'overview');
$adminManagedUserDetailRoute('/admin/users/{id}/identity', 'identity');
$adminManagedUserDetailRoute('/admin/users/{id}/contacts', 'contacts');
$adminManagedUserDetailRoute('/admin/users/{id}/account', 'account');
$adminManagedUserDetailRoute('/admin/users/{id}/access', 'access');
$adminManagedUserDetailRoute('/admin/users/{id}/appointments', 'appointments');
