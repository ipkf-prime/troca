<?php

$router->get(
    '/admin/system/help-texts',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard
    ) {
        $context = $adminGuard(
            $response,
            '/admin/system/help-texts'
        );

        if (!is_array($context)) {
            return $context;
        }

        return $adminRender(
            $response,
            'help-texts',
            [
                'title' =>
                    'مدیریت راهنماها',
                'context' =>
                    $context,
            ]
        );
    }
);
