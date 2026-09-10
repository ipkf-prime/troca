<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use IPKF\Http\Request;
use IPKF\Http\Response;
use RuntimeException;
use Throwable;


/**
 * Shared finite HTTP error presenter.
 *
 * Database content controls presentation values.
 * HTTP status and executable actions remain code-controlled.
 */
final class UiContentHttpPresenter
{
    private UiContentResolver $resolver;

    private UiContentRequestContextService $contexts;

    private UiContentEmergencyCatalog $emergency;

    private UiContentPresentationBrandingService $branding;


    public function __construct(
        ?UiContentResolver $resolver = null,
        ?UiContentRequestContextService $contexts = null,
        ?UiContentEmergencyCatalog $emergency = null,
        ?UiContentPresentationBrandingService $branding = null
    ) {
        $this->emergency =
            $emergency
            ?? new UiContentEmergencyCatalog();


        $this->resolver =
            $resolver
            ?? new UiContentResolver(
                null,
                $this->emergency
            );


        $this->contexts =
            $contexts
            ?? new UiContentRequestContextService();


        $this->branding =
            $branding
            ?? new UiContentPresentationBrandingService();
    }


    public function render(
        Request $request,
        Response $response,
        string $contentKey,
        int $httpStatus,
        ?string $moduleKey = null,
        array $scopePath = []
    ): Response {

        $httpStatus =
            $this->normalizeHttpStatus(
                $httpStatus
            );


        try {

            $envelope =
                $this->contexts
                    ->resolve(
                        $request->uri(),
                        $request->host(),
                        $moduleKey,
                        $scopePath,
                        'fa'
                    );


            $context =
                $envelope['context']
                ?? null;


            if (
                !$context
                instanceof UiContentContext
            ) {
                throw new RuntimeException(
                    'ui_content_context_unavailable'
                );
            }


            $module =
                is_array(
                    $envelope['module']
                    ?? null
                )
                    ? $envelope['module']
                    : [];


            $scopeMetadata =
                is_array(
                    $envelope[
                        'scope_metadata'
                    ]
                    ?? null
                )
                    ? $envelope[
                        'scope_metadata'
                    ]
                    : [];

        } catch (Throwable) {

            $context =
                new UiContentContext(
                    $moduleKey
                    ?: 'core',
                    'fa'
                );


            $module = [
                'module_key' =>
                    $context->moduleKey(),

                'display_name' =>
                    'سامانه',

                'route_path' =>
                    '/',

                'icon_code' =>
                    'apps',

                'color_code' =>
                    null,

                'source' =>
                    'presenter_fallback',
            ];


            $scopeMetadata = [];
        }


        try {

            $page =
                $this->resolver
                    ->resolve(
                        $contentKey,
                        $context,
                        $httpStatus
                    );

        } catch (Throwable) {

            $page =
                $this->emergency
                    ->forHttpStatus(
                        $httpStatus
                    );


            $page['content_key'] =
                $contentKey;

            $page['http_status'] =
                $httpStatus;

            $page['source'] =
                'presenter_emergency';
        }


        if (
            ($page['visible'] ?? true)
            === false
        ) {
            $page =
                $this->emergency
                    ->forHttpStatus(
                        $httpStatus
                    );


            $page['content_key'] =
                $contentKey;

            $page['http_status'] =
                $httpStatus;

            $page['source'] =
                'hidden_content_emergency';
        }


        $severity =
            $this->severity(
                (string) (
                    $page[
                        'severity_code'
                    ]
                    ?? ''
                )
            );


        $layout =
            $this->layout(
                (string) (
                    $page[
                        'layout_variant'
                    ]
                    ?? ''
                )
            );


        $actions = [];


        foreach ([
            [
                'code' =>
                    $page[
                        'primary_action_code'
                    ]
                    ?? null,

                'label' =>
                    $page[
                        'primary_action_label'
                    ]
                    ?? null,

                'kind' =>
                    'primary',
            ],

            [
                'code' =>
                    $page[
                        'secondary_action_code'
                    ]
                    ?? null,

                'label' =>
                    $page[
                        'secondary_action_label'
                    ]
                    ?? null,

                'kind' =>
                    'secondary',
            ],
        ] as $candidate) {

            $action =
                $this->action(
                    $candidate,
                    $module
                );


            if ($action !== null) {
                $actions[] = $action;
            }
        }


        try {

            $branding =
                $this->branding
                    ->resolve(
                        $page,
                        $module,
                        $scopeMetadata
                    );

        } catch (Throwable) {

            $branding = [
                'platform_brand_name' =>
                    'سامانه',

                'brand_title' =>
                    trim(
                        (string) (
                            $module[
                                'display_name'
                            ]
                            ?? 'سامانه'
                        )
                    ),

                'brand_subtitle' =>
                    '',

                'breadcrumbs' =>
                    [],

                'logo_enabled' =>
                    false,

                'logo_url' =>
                    null,

                'logo_alt' =>
                    '',

                'font_family' =>
                    '"Vazirmatn", "IRANSans", "Tahoma", "Segoe UI", sans-serif',

                'admin_css_url' =>
                    '/assets/admin/css/admin.css',

                'accent' =>
                    '#1f6f4a',

                'accent_soft' =>
                    '#e4f0e8',

                'background' =>
                    '#f4f8f5',

                'surface' =>
                    '#ffffff',

                'text' =>
                    '#16241d',

                'muted' =>
                    '#5f7169',

                'border' =>
                    '#cadfd2',

                'theme_source' =>
                    'presenter_fallback',
            ];
        }


        $model = [
            'status' =>
                $httpStatus,

            'page' =>
                $page,

            'module' =>
                $module,

            'scope_metadata' =>
                $scopeMetadata,

            'severity' =>
                $severity,

            'layout' =>
                $layout,

            'actions' =>
                $actions,

            'branding' =>
                $branding,
        ];


        $view =
            BASE_PATH
            . '/resources/views/shared/'
            . 'ui-content-error.php';


        if (!is_readable($view)) {
            return
                $this->plainFallback(
                    $response,
                    $httpStatus,
                    (string) (
                        $page['title']
                        ?? 'خطای سامانه'
                    )
                );
        }


        $level =
            ob_get_level();


        try {

            ob_start();

            require $view;

            $content =
                ob_get_clean()
                ?: '';

        } catch (Throwable) {

            while (
                ob_get_level()
                > $level
            ) {
                ob_end_clean();
            }


            return
                $this->plainFallback(
                    $response,
                    $httpStatus,
                    (string) (
                        $page['title']
                        ?? 'خطای سامانه'
                    )
                );
        }


        return
            $response
                ->status(
                    $httpStatus
                )
                ->header(
                    'Content-Type',
                    'text/html; charset=UTF-8'
                )
                ->header(
                    'Cache-Control',
                    'private, no-store, no-cache, must-revalidate, max-age=0'
                )
                ->header(
                    'Pragma',
                    'no-cache'
                )
                ->header(
                    'X-Content-Type-Options',
                    'nosniff'
                )
                ->header(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->send(
                    $content
                );
    }


    private function action(
        array $candidate,
        array $module
    ): ?array {

        $code =
            strtolower(
                trim(
                    (string) (
                        $candidate['code']
                        ?? ''
                    )
                )
            );


        if ($code === '') {
            return null;
        }


        $label =
            trim(
                (string) (
                    $candidate['label']
                    ?? ''
                )
            );


        if ($label === '') {

            $label =
                match ($code) {

                    'back' =>
                        'بازگشت',

                    'home' =>
                        'صفحه اصلی',

                    'admin_home' =>
                        'داشبورد',

                    'module_home' =>
                        'صفحه اصلی ماژول',

                    default =>
                        '',
                };
        }


        if ($label === '') {
            return null;
        }


        $label =
            mb_substr(
                $label,
                0,
                120
            );


        $style =
            ($candidate['kind'] ?? '')
            === 'primary'
                ? 'primary'
                : 'secondary';


        if ($code === 'back') {

            $fallback =
                $this->safeInternalPath(
                    (string) (
                        $module[
                            'route_path'
                        ]
                        ?? ''
                    )
                )
                ?? '/';


            return [
                'type' =>
                    'back',

                'label' =>
                    $label,

                'href' =>
                    null,

                'fallback' =>
                    $fallback,

                'style' =>
                    $style,
            ];
        }


        if ($code === 'home') {

            return [
                'type' =>
                    'link',

                'label' =>
                    $label,

                'href' =>
                    '/',

                'fallback' =>
                    null,

                'style' =>
                    $style,
            ];
        }


        if ($code === 'admin_home') {

            return [
                'type' =>
                    'link',

                'label' =>
                    $label,

                'href' =>
                    '/admin/dashboard',

                'fallback' =>
                    null,

                'style' =>
                    $style,
            ];
        }


        if ($code === 'module_home') {

            $path =
                $this->safeInternalPath(
                    (string) (
                        $module[
                            'route_path'
                        ]
                        ?? ''
                    )
                );


            if ($path === null) {
                return null;
            }


            return [
                'type' =>
                    'link',

                'label' =>
                    $label,

                'href' =>
                    $path,

                'fallback' =>
                    null,

                'style' =>
                    $style,
            ];
        }


        return null;
    }


    private function safeInternalPath(
        string $path
    ): ?string {

        $path =
            trim(
                $path
            );


        if (
            $path === ''
            ||
            !str_starts_with(
                $path,
                '/'
            )
            ||
            str_starts_with(
                $path,
                '//'
            )
            ||
            str_contains(
                $path,
                '://'
            )
            ||
            str_contains(
                $path,
                '\\'
            )
            ||
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $path
            )
        ) {
            return null;
        }


        return $path;
    }


    private function severity(
        string $severity
    ): string {

        $severity =
            strtolower(
                trim(
                    $severity
                )
            );


        return
            in_array(
                $severity,
                [
                    'information',
                    'warning',
                    'danger',
                    'success',
                ],
                true
            )
                ? $severity
                : 'information';
    }


    private function layout(
        string $layout
    ): string {

        $layout =
            strtolower(
                trim(
                    $layout
                )
            );


        return
            in_array(
                $layout,
                [
                    'system-message',
                    'compact',
                    'wide',
                ],
                true
            )
                ? $layout
                : 'system-message';
    }


    private function normalizeHttpStatus(
        int $status
    ): int {

        return
            $status >= 400
            && $status <= 599
                ? $status
                : 500;
    }


    private function plainFallback(
        Response $response,
        int $status,
        string $title
    ): Response {

        $title =
            trim(
                $title
            );


        if ($title === '') {
            $title =
                'خطای سامانه';
        }


        $escaped =
            htmlspecialchars(
                $title,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );


        return
            $response
                ->status(
                    $status
                )
                ->header(
                    'Content-Type',
                    'text/html; charset=UTF-8'
                )
                ->header(
                    'Cache-Control',
                    'private, no-store, max-age=0'
                )
                ->send(
                    '<!doctype html>'
                    . '<html lang="fa-IR" dir="rtl">'
                    . '<head>'
                    . '<meta charset="UTF-8">'
                    . '<meta name="viewport" '
                    . 'content="width=device-width,initial-scale=1">'
                    . '<title>'
                    . $escaped
                    . '</title>'
                    . '</head>'
                    . '<body dir="rtl">'
                    . '<main><h1>'
                    . $escaped
                    . '</h1></main>'
                    . '</body></html>'
                );
    }
}
