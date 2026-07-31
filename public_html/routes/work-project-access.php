<?php

/**
 * Project-scoped Work routes.
 *
 * This file is loaded after web.php and the other Work route slices. The
 * Router stores routes by method and normalized URI, so these definitions
 * replace the earlier broad RBAC-only Work routes with project-scoped guards.
 */

$workAccessService = static fn (): \App\Services\Work\WorkProjectAccessService =>
    new \App\Services\Work\WorkProjectAccessService();

$workDeny = static function (
    $response,
    callable $adminRender,
    array $context,
    string $title,
    string $message,
    int $status = 403
) {
    return $adminRender($response, 'placeholder', [
        'title' => $title,
        'context' => $context,
        'message' => $message,
    ], $status);
};

$workProjectUrl = static fn (string $reference): string =>
    '/admin/work/projects/' . rawurlencode($reference);

$workItemUrl = static fn (string $projectReference, string $itemReference): string =>
    '/admin/work/projects/' . rawurlencode($projectReference)
    . '/items/' . rawurlencode($itemReference);

$router->get('/admin/work', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/work');
    if (!is_array($context)) {
        return $context;
    }

    try {
        $dashboard = (new \App\Services\Work\WorkDashboardService())->view(
            $request->all(),
            (int) $context['user_id']
        );
    } catch (\Throwable) {
        return $adminRender($response, 'placeholder', [
            'title' => 'داشبورد مدیریت کار',
            'context' => $context,
            'message' => 'داشبورد مدیریت کار در حال حاضر در دسترس نیست.',
        ], 503);
    }

    return $adminRender($response, 'work-dashboard', [
        'title' => 'داشبورد مدیریت کار',
        'context' => $context,
        'dashboard' => $dashboard,
    ]);
});

$router->get('/admin/work/projects', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService
) {
    $context = $adminGuard($response, '/admin/work/projects');
    if (!is_array($context)) {
        return $context;
    }

    try {
        $list = $workAccessService()->projectIndex(
            (int) $context['user_id'],
            $request->all()
        );
    } catch (\Throwable) {
        return $adminRender($response, 'placeholder', [
            'title' => 'پروژه‌های مدیریت کار',
            'context' => $context,
            'message' => 'فهرست پروژه‌های مدیریت کار در حال حاضر در دسترس نیست.',
        ], 503);
    }

    return $adminRender($response, 'work-projects', [
        'title' => 'پروژه‌های مدیریت کار',
        'context' => $context,
        'list' => $list,
    ]);
});

$router->get('/admin/work/projects/{public_reference}', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['found'] ?? false) !== true || ($access['can_view'] ?? false) !== true) {
        return $workDeny(
            $response,
            $adminRender,
            $context,
            'پروژه پیدا نشد',
            'پروژه مورد نظر پیدا نشد یا در دسترس شما نیست.',
            404
        );
    }

    try {
        $result = (new \App\Services\Work\WorkProjectService())->detail($reference);
    } catch (\Throwable) {
        return $workDeny(
            $response,
            $adminRender,
            $context,
            'جزئیات پروژه',
            'اطلاعات پروژه در حال حاضر در دسترس نیست.',
            503
        );
    }

    if (($result['ok'] ?? false) !== true) {
        return $workDeny(
            $response,
            $adminRender,
            $context,
            'پروژه پیدا نشد',
            'پروژه مورد نظر پیدا نشد.',
            404
        );
    }

    return $adminRender($response, 'work-project-show', [
        'title' => (string) ($result['project']['title'] ?? 'جزئیات پروژه'),
        'context' => $context,
        'project' => $result['project'],
        'access' => $access,
    ]);
});

$router->get('/admin/work/projects/{public_reference}/edit', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_manage_project'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ویرایش این پروژه را ندارید.');
    }

    try {
        $result = (new \App\Services\Work\WorkProjectService())->form($reference);
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'ویرایش پروژه', 'فرم ویرایش پروژه در دسترس نیست.', 503);
    }

    if (($result['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'پروژه پیدا نشد', 'پروژه مورد نظر پیدا نشد.', 404);
    }

    return $adminRender($response, 'work-project-form', [
        'title' => 'ویرایش پروژه',
        'context' => $context,
        'form' => $result['form'],
        'options' => $result['options'],
        'errors' => [],
        'isEdit' => true,
        'access' => $access,
    ]);
});

$router->post('/admin/work/projects/{public_reference}', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny,
    $workProjectUrl
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_manage_project'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ویرایش این پروژه را ندارید.');
    }

    try {
        $service = new \App\Services\Work\WorkProjectService();
        $result = $service->update(
            $reference,
            $request->all(),
            (int) $context['user_id'],
            $context
        );

        if (($result['ok'] ?? false) === true) {
            return $response->redirect($workProjectUrl($reference) . '?saved=1');
        }

        $formResult = $service->form($reference);
        return $adminRender($response, 'work-project-form', [
            'title' => 'ویرایش پروژه',
            'context' => $context,
            'form' => ($result['form'] ?? []) + ($formResult['form'] ?? []),
            'options' => $formResult['options'] ?? [],
            'errors' => $result['errors'] ?? ['invalid' => 'اطلاعات پروژه معتبر نیست.'],
            'isEdit' => true,
            'access' => $access,
        ], 422);
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'ویرایش پروژه', 'ذخیره تغییرات پروژه انجام نشد.', 503);
    }
});

foreach (['archive', 'restore'] as $projectOperation) {
    $router->post(
        '/admin/work/projects/{public_reference}/' . $projectOperation,
        function ($request, $response) use (
            $adminRender,
            $adminGuard,
            $workAccessService,
            $workDeny,
            $workProjectUrl,
            $projectOperation
        ) {
            $context = $adminGuard($response, '/admin/work/projects/{public_reference}/edit');
            if (!is_array($context)) {
                return $context;
            }

            $reference = (string) $request->route('public_reference');
            $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

            if (($access['can_manage_project'] ?? false) !== true) {
                return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه مدیریت این پروژه را ندارید.');
            }

            $service = new \App\Services\Work\WorkProjectService();
            $ok = $projectOperation === 'archive'
                ? $service->archive($reference, (int) $context['user_id'], $context)
                : $service->restore($reference, (int) $context['user_id'], $context);

            if (!$ok) {
                return $workDeny($response, $adminRender, $context, 'عملیات پروژه', 'عملیات درخواستی انجام نشد.', 422);
            }

            return $projectOperation === 'archive'
                ? $response->redirect('/admin/work/projects?status=archived')
                : $response->redirect($workProjectUrl($reference));
        }
    );
}

$router->get('/admin/work/projects/{public_reference}/items', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_view'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'پروژه پیدا نشد', 'پروژه مورد نظر در دسترس شما نیست.', 404);
    }

    try {
        $page = (new \App\Services\Work\WorkItemService())->index(
            $reference,
            $request->all()
        );
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'کارها و تسک‌ها', 'فهرست کارها در دسترس نیست.', 503);
    }

    if (($page['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'پروژه پیدا نشد', 'پروژه مورد نظر پیدا نشد.', 404);
    }

    return $adminRender($response, 'work-items', [
        'title' => 'کارها و تسک‌ها',
        'context' => $context,
        'page' => $page,
        'access' => $access,
    ]);
});

$router->get('/admin/work/projects/{public_reference}/items/create', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/create');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_create_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ایجاد آیتم در این پروژه را ندارید.');
    }

    $page = (new \App\Services\Work\WorkItemService())->form($reference);
    if (($page['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'پروژه پیدا نشد', 'پروژه مورد نظر پیدا نشد.', 404);
    }

    return $adminRender($response, 'work-item-form', [
        'title' => 'ایجاد آیتم',
        'context' => $context,
        'project' => $page['project'],
        'form' => $page['form'],
        'options' => $page['options'],
        'errors' => [],
        'isEdit' => false,
        'access' => $access,
    ]);
});

$router->post('/admin/work/projects/{public_reference}/items', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/create');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_create_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ایجاد آیتم در این پروژه را ندارید.');
    }

    try {
        $service = new \App\Services\Work\WorkItemService();
        $result = $service->create(
            $reference,
            $request->all(),
            (int) $context['user_id'],
            $context
        );

        if (($result['ok'] ?? false) === true) {
            return $response->redirect(
                '/admin/work/projects/' . rawurlencode($reference)
                . '/items/' . rawurlencode((string) $result['public_reference'])
                . '?saved=1'
            );
        }

        $page = $service->form($reference);
        return $adminRender($response, 'work-item-form', [
            'title' => 'ایجاد آیتم',
            'context' => $context,
            'project' => $page['project'] ?? [],
            'form' => ($result['form'] ?? []) + ($page['form'] ?? []),
            'options' => $page['options'] ?? [],
            'errors' => $result['errors'] ?? ['invalid' => 'اطلاعات واردشده معتبر نیست.'],
            'isEdit' => false,
            'access' => $access,
        ], 422);
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'ایجاد آیتم', 'ثبت آیتم انجام نشد.', 503);
    }
});

$router->get('/admin/work/projects/{public_reference}/items/{item_reference}/edit', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $access = $workAccessService()->itemAccess(
        $projectReference,
        $itemReference,
        (int) $context['user_id']
    );

    if (($access['can_edit_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ویرایش این آیتم را ندارید.');
    }

    $page = (new \App\Services\Work\WorkItemService())->form(
        $projectReference,
        $itemReference
    );

    if (($page['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'آیتم پیدا نشد', 'آیتم مورد نظر پیدا نشد.', 404);
    }

    return $adminRender($response, 'work-item-form', [
        'title' => 'ویرایش آیتم',
        'context' => $context,
        'project' => $page['project'],
        'form' => $page['form'],
        'options' => $page['options'],
        'errors' => [],
        'isEdit' => true,
        'access' => $access,
    ]);
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny,
    $workItemUrl
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $access = $workAccessService()->itemAccess(
        $projectReference,
        $itemReference,
        (int) $context['user_id']
    );

    if (($access['can_edit_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ویرایش این آیتم را ندارید.');
    }

    try {
        $service = new \App\Services\Work\WorkItemService();
        $result = $service->update(
            $projectReference,
            $itemReference,
            $request->all(),
            (int) $context['user_id'],
            $context
        );

        if (($result['ok'] ?? false) === true) {
            return $response->redirect($workItemUrl($projectReference, $itemReference) . '?saved=1');
        }

        $page = $service->form($projectReference, $itemReference);
        return $adminRender($response, 'work-item-form', [
            'title' => 'ویرایش آیتم',
            'context' => $context,
            'project' => $page['project'] ?? [],
            'form' => ($result['form'] ?? []) + ($page['form'] ?? []),
            'options' => $page['options'] ?? [],
            'errors' => $result['errors'] ?? ['invalid' => 'اطلاعات واردشده معتبر نیست.'],
            'isEdit' => true,
            'access' => $access,
        ], 422);
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'ویرایش آیتم', 'ذخیره تغییرات آیتم انجام نشد.', 503);
    }
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}/archive', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $access = $workAccessService()->itemAccess(
        $projectReference,
        $itemReference,
        (int) $context['user_id']
    );

    if (($access['can_archive_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه بایگانی این آیتم را ندارید.');
    }

    $archived = (new \App\Services\Work\WorkItemService())->archive(
        $projectReference,
        $itemReference,
        (int) $context['user_id'],
        $context
    );

    if (!$archived) {
        return $workDeny($response, $adminRender, $context, 'بایگانی آیتم', 'آیتمی که زیرمجموعه فعال دارد قابل بایگانی نیست.', 422);
    }

    return $response->redirect(
        '/admin/work/projects/' . rawurlencode($projectReference) . '/items?archived=1'
    );
});

$router->get('/admin/work/projects/{public_reference}/members', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/members');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_manage_members'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه مدیریت اعضای این پروژه را ندارید.');
    }

    $page = (new \App\Services\Work\WorkProjectMemberService())->view($reference);
    if (($page['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'پروژه پیدا نشد', 'پروژه مورد نظر پیدا نشد.', 404);
    }

    return $adminRender($response, 'work-project-members', [
        'title' => 'اعضای پروژه',
        'context' => $context,
        'page' => $page,
        'errors' => [],
        'access' => $access,
    ]);
});

$router->post('/admin/work/projects/{public_reference}/members', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/members');
    if (!is_array($context)) {
        return $context;
    }

    $reference = (string) $request->route('public_reference');
    $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

    if (($access['can_manage_members'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه مدیریت اعضای این پروژه را ندارید.');
    }

    $service = new \App\Services\Work\WorkProjectMemberService();
    $result = $service->add(
        $reference,
        $request->all(),
        (int) $context['user_id'],
        $context
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            '/admin/work/projects/' . rawurlencode($reference) . '/members?saved=1'
        );
    }

    $page = $service->view($reference);
    return $adminRender($response, 'work-project-members', [
        'title' => 'اعضای پروژه',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? ['member' => 'ثبت عضو انجام نشد.'],
        'access' => $access,
    ], 422);
});

foreach (['role', 'remove'] as $memberOperation) {
    $suffix = $memberOperation === 'role' ? '/role' : '/remove';

    $router->post(
        '/admin/work/projects/{public_reference}/members/{member_id}' . $suffix,
        function ($request, $response) use (
            $adminRender,
            $adminGuard,
            $workAccessService,
            $workDeny,
            $memberOperation
        ) {
            $context = $adminGuard($response, '/admin/work/projects/{public_reference}/members');
            if (!is_array($context)) {
                return $context;
            }

            $reference = (string) $request->route('public_reference');
            $access = $workAccessService()->projectAccess($reference, (int) $context['user_id']);

            if (($access['can_manage_members'] ?? false) !== true) {
                return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه مدیریت اعضای این پروژه را ندارید.');
            }

            $service = new \App\Services\Work\WorkProjectMemberService();
            $ok = $memberOperation === 'role'
                ? $service->updateRole(
                    $reference,
                    (int) $request->route('member_id'),
                    $request->all(),
                    (int) $context['user_id'],
                    $context
                )
                : $service->remove(
                    $reference,
                    (int) $request->route('member_id'),
                    (int) $context['user_id'],
                    $context
                );

            return $response->redirect(
                '/admin/work/projects/' . rawurlencode($reference)
                . '/members?' . ($ok ? ($memberOperation === 'role' ? 'updated=1' : 'removed=1') : 'error=1')
            );
        }
    );
}

$router->get('/admin/work/projects/{public_reference}/items/{item_reference}', function ($request, $response) use (
    $adminRender,
    $adminGuard,
    $workAccessService,
    $workDeny
) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $access = $workAccessService()->itemAccess(
        $projectReference,
        $itemReference,
        (int) $context['user_id']
    );

    if (($access['can_view_item'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'آیتم پیدا نشد', 'آیتم مورد نظر پیدا نشد یا در دسترس شما نیست.', 404);
    }

    try {
        $page = (new \App\Services\Work\WorkItemDetailService())->view(
            $projectReference,
            $itemReference
        );
    } catch (\Throwable) {
        return $workDeny($response, $adminRender, $context, 'جزئیات کار', 'جزئیات کار در دسترس نیست.', 503);
    }

    if (($page['ok'] ?? false) !== true) {
        return $workDeny($response, $adminRender, $context, 'آیتم پیدا نشد', 'آیتم مورد نظر پیدا نشد.', 404);
    }

    if (($access['can_view_audit'] ?? false) !== true) {
        $page['activity'] = [];
        $page['activity_hidden'] = true;
    }

    return $adminRender($response, 'work-item-show', [
        'title' => (string) ($page['item']['title'] ?? 'جزئیات کار'),
        'context' => $context,
        'page' => $page,
        'access' => $access,
    ]);
});

$workContributionRoutes = [
    'comments' => static function ($service, $request, $projectReference, $itemReference, $context) {
        return $service->addComment(
            $projectReference,
            $itemReference,
            (string) $request->input('body', ''),
            (int) $context['user_id'],
            $context
        );
    },
    'checklist' => static function ($service, $request, $projectReference, $itemReference, $context) {
        return $service->addChecklist(
            $projectReference,
            $itemReference,
            (string) $request->input('title', ''),
            (int) $context['user_id'],
            $context
        );
    },
    'attachments' => static function ($service, $request, $projectReference, $itemReference, $context) {
        return $service->uploadAttachment(
            $projectReference,
            $itemReference,
            is_array($_FILES['attachment'] ?? null) ? $_FILES['attachment'] : [],
            (int) $context['user_id'],
            $context
        );
    },
];

foreach ($workContributionRoutes as $segment => $operation) {
    $router->post(
        '/admin/work/projects/{public_reference}/items/{item_reference}/' . $segment,
        function ($request, $response) use (
            $adminRender,
            $adminGuard,
            $workAccessService,
            $workDeny,
            $workItemUrl,
            $operation
        ) {
            $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
            if (!is_array($context)) {
                return $context;
            }

            $projectReference = (string) $request->route('public_reference');
            $itemReference = (string) $request->route('item_reference');
            $access = $workAccessService()->itemAccess(
                $projectReference,
                $itemReference,
                (int) $context['user_id']
            );

            if (($access['can_contribute_item'] ?? false) !== true) {
                return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه ثبت تغییر روی این آیتم را ندارید.');
            }

            $service = new \App\Services\Work\WorkItemDetailService();

            try {
                $result = $operation($service, $request, $projectReference, $itemReference, $context);
            } catch (\Throwable) {
                $result = ['ok' => false, 'error' => 'failed'];
            }

            $url = $workItemUrl($projectReference, $itemReference);
            return $response->redirect(
                $url . (($result['ok'] ?? false)
                    ? '?saved=1'
                    : '?error=' . rawurlencode($service->errorMessage((string) ($result['error'] ?? 'failed'))))
            );
        }
    );
}

$router->post(
    '/admin/work/projects/{public_reference}/items/{item_reference}/checklist/{checklist_id}/toggle',
    function ($request, $response) use (
        $adminRender,
        $adminGuard,
        $workAccessService,
        $workDeny,
        $workItemUrl
    ) {
        $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
        if (!is_array($context)) {
            return $context;
        }

        $projectReference = (string) $request->route('public_reference');
        $itemReference = (string) $request->route('item_reference');
        $access = $workAccessService()->itemAccess(
            $projectReference,
            $itemReference,
            (int) $context['user_id']
        );

        if (($access['can_contribute_item'] ?? false) !== true) {
            return $workDeny($response, $adminRender, $context, 'دسترسی غیرمجاز', 'اجازه تغییر چک‌لیست را ندارید.');
        }

        $ok = (new \App\Services\Work\WorkItemDetailService())->toggleChecklist(
            $projectReference,
            $itemReference,
            (int) $request->route('checklist_id'),
            (string) $request->input('completed', '0') === '1',
            (int) $context['user_id'],
            $context
        );

        return $response->redirect(
            $workItemUrl($projectReference, $itemReference)
            . ($ok ? '?saved=1' : '?error=' . rawurlencode('تغییر وضعیت چک‌لیست انجام نشد.'))
        );
    }
);

$router->get(
    '/admin/work/projects/{public_reference}/items/{item_reference}/attachments/{attachment_reference}',
    function ($request, $response) use (
        $adminRender,
        $adminGuard,
        $workAccessService,
        $workDeny
    ) {
        $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items');
        if (!is_array($context)) {
            return $context;
        }

        $projectReference = (string) $request->route('public_reference');
        $itemReference = (string) $request->route('item_reference');
        $access = $workAccessService()->itemAccess(
            $projectReference,
            $itemReference,
            (int) $context['user_id']
        );

        if (($access['can_view_item'] ?? false) !== true) {
            return $workDeny($response, $adminRender, $context, 'فایل پیدا نشد', 'فایل مورد نظر پیدا نشد یا در دسترس شما نیست.', 404);
        }

        try {
            $attachment = (new \App\Services\Work\WorkItemDetailService())->download(
                $projectReference,
                $itemReference,
                (string) $request->route('attachment_reference')
            );
        } catch (\Throwable) {
            $attachment = null;
        }

        if ($attachment === null) {
            return $response->status(404)->send('404 - File not found');
        }

        $originalName = (string) ($attachment['original_name'] ?? 'attachment');
        $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'attachment';
        $content = file_get_contents((string) $attachment['path']);

        if ($content === false) {
            return $response->status(404)->send('404 - File not found');
        }

        return $response
            ->header('Content-Type', (string) ($attachment['mime_type'] ?? 'application/octet-stream'))
            ->header('Content-Length', (string) strlen($content))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header(
                'Content-Disposition',
                'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName)
            )
            ->send($content);
    }
);
