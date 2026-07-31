<?php

$itemDetailUrl = static function (string $projectReference, string $itemReference): string {
    return '/admin/work/projects/' . rawurlencode($projectReference)
        . '/items/' . rawurlencode($itemReference);
};

$router->get('/admin/work/projects/{public_reference}/items/{item_reference}', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items');
    if (!is_array($context)) {
        return $context;
    }

    try {
        $page = (new \App\Services\Work\WorkItemDetailService())->view(
            (string) $request->route('public_reference'),
            (string) $request->route('item_reference')
        );
    } catch (\Throwable) {
        return $adminRender($response, 'placeholder', [
            'title' => 'جزئیات کار',
            'context' => $context,
            'message' => 'جزئیات کار در حال حاضر در دسترس نیست.',
        ], 503);
    }

    if (($page['ok'] ?? false) !== true) {
        return $adminRender($response, 'placeholder', [
            'title' => 'آیتم پیدا نشد',
            'context' => $context,
            'message' => 'آیتم مورد نظر پیدا نشد.',
        ], 404);
    }

    return $adminRender($response, 'work-item-show', [
        'title' => (string) ($page['item']['title'] ?? 'جزئیات کار'),
        'context' => $context,
        'page' => $page,
    ]);
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}/comments', function ($request, $response) use ($adminGuard, $itemDetailUrl) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $service = new \App\Services\Work\WorkItemDetailService();

    try {
        $result = $service->addComment(
            $projectReference,
            $itemReference,
            (string) $request->input('body', ''),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'error' => 'failed'];
    }

    $url = $itemDetailUrl($projectReference, $itemReference);
    return $response->redirect(
        $url . (($result['ok'] ?? false)
            ? '?saved=1'
            : '?error=' . rawurlencode($service->errorMessage((string) ($result['error'] ?? 'failed'))))
    );
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}/checklist', function ($request, $response) use ($adminGuard, $itemDetailUrl) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $service = new \App\Services\Work\WorkItemDetailService();

    try {
        $result = $service->addChecklist(
            $projectReference,
            $itemReference,
            (string) $request->input('title', ''),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'error' => 'failed'];
    }

    $url = $itemDetailUrl($projectReference, $itemReference);
    return $response->redirect(
        $url . (($result['ok'] ?? false)
            ? '?saved=1'
            : '?error=' . rawurlencode($service->errorMessage((string) ($result['error'] ?? 'failed'))))
    );
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}/checklist/{checklist_id}/toggle', function ($request, $response) use ($adminGuard, $itemDetailUrl) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');

    try {
        $ok = (new \App\Services\Work\WorkItemDetailService())->toggleChecklist(
            $projectReference,
            $itemReference,
            (int) $request->route('checklist_id'),
            (string) $request->input('completed', '0') === '1',
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $ok = false;
    }

    $url = $itemDetailUrl($projectReference, $itemReference);
    return $response->redirect($url . ($ok ? '?saved=1' : '?error=' . rawurlencode('تغییر وضعیت چک‌لیست انجام نشد.')));
});

$router->post('/admin/work/projects/{public_reference}/items/{item_reference}/attachments', function ($request, $response) use ($adminGuard, $itemDetailUrl) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items/{item_reference}/edit');
    if (!is_array($context)) {
        return $context;
    }

    $projectReference = (string) $request->route('public_reference');
    $itemReference = (string) $request->route('item_reference');
    $service = new \App\Services\Work\WorkItemDetailService();

    try {
        $result = $service->uploadAttachment(
            $projectReference,
            $itemReference,
            is_array($_FILES['attachment'] ?? null) ? $_FILES['attachment'] : [],
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'error' => 'failed'];
    }

    $url = $itemDetailUrl($projectReference, $itemReference);
    return $response->redirect(
        $url . (($result['ok'] ?? false)
            ? '?saved=1'
            : '?error=' . rawurlencode($service->errorMessage((string) ($result['error'] ?? 'failed'))))
    );
});

$router->get('/admin/work/projects/{public_reference}/items/{item_reference}/attachments/{attachment_reference}', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/work/projects/{public_reference}/items');
    if (!is_array($context)) {
        return $context;
    }

    try {
        $attachment = (new \App\Services\Work\WorkItemDetailService())->download(
            (string) $request->route('public_reference'),
            (string) $request->route('item_reference'),
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
});
