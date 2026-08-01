<?php

$router->post(
    '/admin/profile/avatar',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/profile'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\ProfileAvatarService()
        )->store(
            (int) $context['user_id'],
            is_array($_FILES['avatar'] ?? null)
                ? $_FILES['avatar']
                : []
        );

        return $response->redirect(
            '/admin/profile?status='
            . rawurlencode(
                ($result['ok'] ?? false)
                    ? 'avatar_saved'
                    : (string) (
                        $result['status']
                        ?? 'avatar_save_failed'
                    )
            )
        );
    }
);

$router->post(
    '/admin/profile/avatar/remove',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/profile'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\ProfileAvatarService()
        )->remove((int) $context['user_id']);

        return $response->redirect(
            '/admin/profile?status='
            . rawurlencode(
                ($result['ok'] ?? false)
                    ? 'avatar_removed'
                    : (string) (
                        $result['status']
                        ?? 'avatar_remove_failed'
                    )
            )
        );
    }
);
