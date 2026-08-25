<?php

declare(strict_types=1);

$router->get(
    '/admin/ticketing',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing'
        );

        if (!is_array($context)) {
            return $context;
        }

        return $adminRender(
            $response,
            'ticketing-dashboard',
            [
                'title' => 'پشتیبانی و تیکتینگ',
                'context' => $context,
                'foundation' => [
                    'application' => 'ticketing',
                    'permission' => 'ticketing.ticket.view',
                    'runtime' => 'foundation',
                ],
            ]
        );
    }
);
