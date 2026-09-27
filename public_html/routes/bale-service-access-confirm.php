<?php

declare(strict_types=1);

/*
 * Service bot confirmation endpoint.
 *
 * Authentication is performed through the HMAC
 * signature of the actual receiving bot token.
 *
 * This route does not use an IPKF user session.
 */
$router->post(
    '/integrations/bale/service-access/confirm',
    function ($request, $response) {

        $response->header(
            'Cache-Control',
            'no-store'
        );

        try {
            $body = (string) file_get_contents(
                'php://input'
            );

            $timestamp = trim((string) (
                $_SERVER['HTTP_X_BALE_TIMESTAMP'] ?? ''
            ));

            $signature = trim((string) (
                $_SERVER['HTTP_X_BALE_SIGNATURE'] ?? ''
            ));

            $confirmed = (
                new \App\Services\BaleServiceAccessConfirmationService()
            )->confirm(
                $body,
                $timestamp,
                $signature
            );

            return $response->json([
                'ok' => true,
                'verified' => $confirmed,
            ]);

        } catch (\Throwable) {
            /*
             * No tokens, message bodies or provider
             * credentials are returned to the caller.
             */
            return $response
                ->status(401)
                ->json([
                    'ok' => false,
                ]);
        }
    }
);
