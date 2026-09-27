<?php

declare(strict_types=1);

/*
 * Browser consent route.
 * A queued request is NOT a completed account association.
 */

$baleLinkPage = static function (
    $response,
    int $status,
    string $message,
    bool $showForm = false
) {
    $csrf = $showForm
        ? (new \IPKF\Security\Csrf())->token()
        : '';

    $safeMessage = htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeCsrf = htmlspecialchars(
        $csrf,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeLinkTokenLabel = htmlspecialchars(
        \App\Services\UiContent\UiContentInlineGuide::bodyText(
            'core.bale-account-link.ui.link_token_label',
            'core',
            'bale-account-link'
        ),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeConsentLabel = htmlspecialchars(
        \App\Services\UiContent\UiContentInlineGuide::bodyText(
            'core.bale-account-link.ui.consent_label',
            'core',
            'bale-account-link'
        ),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeSubmitLabel = htmlspecialchars(
        \App\Services\UiContent\UiContentInlineGuide::bodyText(
            'core.bale-account-link.ui.submit_button',
            'core',
            'bale-account-link'
        ),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safePageTitle = htmlspecialchars(
        \App\Services\UiContent\UiContentInlineGuide::bodyText(
            'core.bale-account-link.ui.browser_page_title',
            'core',
            'bale-account-link'
        ),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $form = '';

    if ($showForm) {
        $form = '
<form action="/integrations/bale/link"
      method="post" autocomplete="off">
    <input type="hidden" name="_token"
           value="' . $safeCsrf . '">

    <label for="link_token">
        ' . $safeLinkTokenLabel . '
    </label>
    <input id="link_token" name="link_token"
           type="text" minlength="64" maxlength="64"
           pattern="[a-f0-9]{64}" required
           autocomplete="off" spellcheck="false">

    <label>
        <input type="checkbox" name="consent"
               value="yes" required>
        ' . $safeConsentLabel . '
    </label>

    <button type="submit">' . $safeSubmitLabel . '</button>
</form>';
    }

    $html = '<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">
<title>' . $safePageTitle . '</title>
</head>
<body>
<h1>' . $safePageTitle . '</h1>
<p>' . $safeMessage . '</p>
' . $form . '
</body>
</html>';

    return $response
        ->status($status)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->header('Cache-Control', 'no-store, max-age=0')
        ->header('Referrer-Policy', 'no-referrer')
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('X-Frame-Options', 'DENY')
        ->header(
            'Content-Security-Policy',
            "default-src 'none'; " .
            "form-action 'self'; base-uri 'none'; " .
            "frame-ancestors 'none'"
        )
        ->send($html);
};

$router->get(
    '/integrations/bale/link',
    static function ($request, $response) use ($baleLinkPage) {
        $context = (
            new \App\Services\BaleAccountLinkSessionService()
        )->currentVerifiedContext();

        if ($context === null) {
            return $baleLinkPage(
                $response,
                403,
                \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.auth_required','core','bale-account-link')
            );
        }

        return $baleLinkPage(
            $response,
            200,
            \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.ui.browser_intro','core','bale-account-link'),
            true
        );
    }
);

$router->post(
    '/integrations/bale/link',
    static function ($request, $response) use ($baleLinkPage) {
        $csrf = (string) $request->input('_token', '');
        $token = (string) $request->input('link_token', '');
        $consent = $request->input('consent', '') === 'yes';

        if (
            !$consent ||
            preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
        ) {
            return $baleLinkPage(
                $response,
                400,
                \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.invalid_token','core','bale-account-link')
            );
        }

        $configPath =
            \App\Services\BaleAccountLinkPrivateStorage::configPath();

        if (
            !is_file($configPath) ||
            is_link($configPath) ||
            (fileperms($configPath) & 0777) !== 0600
        ) {
            return $baleLinkPage(
                $response,
                503,
                \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.service_inactive','core','bale-account-link')
            );
        }

        try {
            $configuration = json_decode(
                (string) file_get_contents($configPath),
                true,
                8,
                JSON_THROW_ON_ERROR
            );

            /*
             * Installing private configuration must not, by itself,
             * activate browser-initiated account linking.
             */
            if (
                !is_array($configuration) ||
                ($configuration['link_enabled'] ?? null) !== true
            ) {
                return $baleLinkPage(
                    $response,
                    503,
                    \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.service_inactive','core','bale-account-link')
                );
            }

            $keyHex = $configuration['assertion_key_hex']
                ?? null;

            $key = (
                is_string($keyHex) &&
                preg_match('/^[a-f0-9]{64}$/D', $keyHex) === 1
            )
                ? hex2bin($keyHex)
                : false;

            if (!is_string($key) || strlen($key) !== 32) {
                throw new \RuntimeException(
                    'PRIVATE_CONFIG_UNAVAILABLE'
                );
            }

            $envelope = (
                new \App\Services\BaleAccountLinkPreparationService()
            )->prepareForCurrentSession(
                $token,
                $csrf,
                true,
                'IPKF',
                $key
            );

            unset($key, $keyHex, $configuration);

            if (
                $envelope === null ||
                !(new \App\Services\BaleAccountLinkOutboxService())
                    ->enqueue($envelope)
            ) {
                return $baleLinkPage(
                    $response,
                    400,
                    \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.submit_failed','core','bale-account-link')
                );
            }

            return $baleLinkPage(
                $response,
                202,
                \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.notice.queued','core','bale-account-link')
            );
        } catch (\Throwable) {
            return $baleLinkPage(
                $response,
                503,
                \App\Services\UiContent\UiContentInlineGuide::bodyText('core.bale-account-link.error.temporarily_unavailable','core','bale-account-link')
            );
        }
    }
);
