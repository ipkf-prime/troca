<?php

$workRuntimeReport = static function (
    \Throwable $exception,
    string $operation,
    array $context = []
): string {
    $incident = strtoupper(
        substr(
            hash(
                'sha256',
                uniqid('', true)
                . '|'
                . (string) getmypid()
                . '|'
                . $operation
            ),
            0,
            12
        )
    );

    $payload = [
        'incident' => $incident,
        'occurred_at' => gmdate('c'),
        'operation' => $operation,
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'context' => $context,
        'trace' => $exception->getTraceAsString(),
    ];

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if (is_string($json)) {
        error_log('IPKF_WORK_RUNTIME ' . $json);

        $directory = BASE_PATH . '/storage/logs';

        if (
            is_dir($directory)
            || @mkdir($directory, 0770, true)
        ) {
            @file_put_contents(
                $directory . '/work-runtime.log',
                $json . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    return $incident;
};

$router->get(
    '/admin/work',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $workRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/work'
        );

        if (!is_array($context)) {
            return $context;
        }

        $filters = [
            'scope' => trim(
                (string) $request->input('scope', 'open')
            ),
            'q' => trim(
                (string) $request->input('q', '')
            ),
        ];

        try {
            $dashboard = (
                new \App\Services\Work\WorkDashboardService()
            )->view(
                $filters,
                (int) $context['user_id']
            );
        } catch (\Throwable $exception) {
            $incident = $workRuntimeReport(
                $exception,
                'dashboard',
                [
                    'user_id' => (int) $context['user_id'],
                    'host' => (string) $request->host(),
                    'uri' => (string) $request->uri(),
                ]
            );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' => 'داشبورد مدیریت کار',
                    'context' => $context,
                    'message' =>
                        'داشبورد مدیریت کار در حال حاضر '
                        . 'در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'work-dashboard',
            [
                'title' => 'داشبورد مدیریت کار',
                'context' => $context,
                'dashboard' => $dashboard,
            ]
        );
    }
);

$router->get(
    '/admin/work/projects',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $workRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/work/projects'
        );

        if (!is_array($context)) {
            return $context;
        }

        $filters = [
            'q' => trim(
                (string) $request->input('q', '')
            ),
            'status' => trim(
                (string) $request->input('status', '')
            ),
        ];

        try {
            $list = (
                new \App\Services\Work\WorkProjectService()
            )->index($filters);
        } catch (\Throwable $exception) {
            $incident = $workRuntimeReport(
                $exception,
                'project_index',
                [
                    'user_id' => (int) $context['user_id'],
                    'host' => (string) $request->host(),
                    'uri' => (string) $request->uri(),
                    'filters' => $filters,
                ]
            );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' => 'پروژه‌های مدیریت کار',
                    'context' => $context,
                    'message' =>
                        'فهرست پروژه‌های مدیریت کار در حال '
                        . 'حاضر در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'work-projects',
            [
                'title' => 'پروژه‌های مدیریت کار',
                'context' => $context,
                'list' => $list,
            ]
        );
    }
);
