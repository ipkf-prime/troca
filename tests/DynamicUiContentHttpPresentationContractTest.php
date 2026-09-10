<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


if (!defined('BASE_PATH')) {
    define(
        'BASE_PATH',
        $root
        . '/public_html'
    );
}


require_once
    BASE_PATH
    . '/system/Http/Request.php';

require_once
    BASE_PATH
    . '/system/Http/Response.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentContext.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentStoreInterface.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentEmergencyCatalog.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentResolver.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentScopeProviderInterface.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentRequestContextService.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentPresentationBrandingService.php';

require_once
    BASE_PATH
    . '/app/Services/UiContent/UiContentHttpPresenter.php';


use App\Services\UiContent\UiContentEmergencyCatalog;
use App\Services\UiContent\UiContentHttpPresenter;
use App\Services\UiContent\UiContentRequestContextService;
use App\Services\UiContent\UiContentResolver;
use App\Services\UiContent\UiContentStoreInterface;
use IPKF\Http\Request;
use IPKF\Http\Response;


$assert =
    static function (
        bool $condition,
        string $message
    ): void {

        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


final class PresentationFakeStore
    implements UiContentStoreInterface
{
    public function available(): bool
    {
        return true;
    }


    public function definition(
        string $contentKey
    ): ?array {

        if ($contentKey !== 'http.404') {
            return null;
        }


        return [
            'id' =>
                1,

            'public_reference' =>
                'UICD-TEST',

            'content_key' =>
                'http.404',

            'content_type' =>
                'error',

            'default_locale' =>
                'fa',

            'http_status' =>
                404,
        ];
    }


    public function activeOverrides(
        int $definitionId,
        array $scopeKeys,
        array $locales,
        \DateTimeImmutable $now
    ): array {

        return [
            [
                'definition_id' =>
                    1,

                'public_reference' =>
                    'UICO-GLOBAL',

                'scope_key' =>
                    'global',

                'locale' =>
                    'fa',

                'title' =>
                    'صفحه پیدا نشد',

                'body' =>
                    'متن عمومی',

                'icon_code' =>
                    'circle-alert',

                'severity_code' =>
                    'information',

                'layout_variant' =>
                    'system-message',

                'visibility_mode' =>
                    'show',

                'primary_action_code' =>
                    'back',

                'primary_action_label' =>
                    'بازگشت',

                'secondary_action_code' =>
                    null,

                'secondary_action_label' =>
                    null,

                'metadata_json' =>
                    null,
            ],

            [
                'definition_id' =>
                    1,

                'public_reference' =>
                    'UICO-WORK',

                'scope_key' =>
                    'module:work',

                'locale' =>
                    'fa',

                'title' =>
                    'صفحه مدیریت کار پیدا نشد',

                'body' =>
                    null,

                'icon_code' =>
                    null,

                'severity_code' =>
                    null,

                'layout_variant' =>
                    null,

                'visibility_mode' =>
                    'inherit',

                'primary_action_code' =>
                    null,

                'primary_action_label' =>
                    null,

                'secondary_action_code' =>
                    'module_home',

                'secondary_action_label' =>
                    'مدیریت کار',

                'metadata_json' =>
                    null,
            ],
        ];
    }
}


$runtimeRows = [
    [
        'module_key' =>
            'work',

        'display_name' =>
            'مدیریت کار و پروژه',

        'route_path' =>
            '/admin/work',

        'color_code' =>
            '#245f8f',

        'icon_code' =>
            'briefcase',

        'is_active' =>
            1,
    ],

    [
        'module_key' =>
            'ticketing',

        'display_name' =>
            'پشتیبانی و تیکتینگ',

        'route_path' =>
            '/admin/ticketing',

        'color_code' =>
            '#258843',

        'icon_code' =>
            'life-buoy',

        'is_active' =>
            1,
    ],
];


$contexts =
    new UiContentRequestContextService(
        null,
        $runtimeRows,
        [
            'work' => [
                'name' =>
                    'مدیریت کار و پروژه',
            ],
        ]
    );


$resolved =
    $contexts->resolve(
        '/admin/work/projects/not-found',
        'example.invalid'
    );


$assert(
    $resolved['context']
        ->moduleKey()
    === 'work',
    'Dynamic module route detection failed.'
);


$resolver =
    new UiContentResolver(
        new PresentationFakeStore(),
        new UiContentEmergencyCatalog()
    );


$presenter =
    new UiContentHttpPresenter(
        $resolver,
        $contexts,
        new UiContentEmergencyCatalog()
    );


$_SERVER['REQUEST_METHOD'] =
    'GET';

$_SERVER['REQUEST_URI'] =
    '/admin/work/projects/not-found';

$_SERVER['HTTP_HOST'] =
    'example.invalid';


$request =
    Request::capture();

$response =
    new Response();


$result =
    $presenter->render(
        $request,
        $response,
        'http.404',
        404
    );


$assert(
    $result->statusCode()
    === 404,
    'Presenter changed HTTP 404.'
);


ob_start();

$result->emit();

$html =
    ob_get_clean()
    ?: '';


$assert(
    str_contains(
        $html,
        'data-ui-content-error="1"'
    ),
    'Shared error marker missing.'
);


$assert(
    str_contains(
        $html,
        'صفحه مدیریت کار پیدا نشد'
    ),
    'Dynamic module title missing.'
);


$assert(
    str_contains(
        $html,
        'مدیریت کار و پروژه'
    ),
    'Dynamic module identity missing.'
);


$router =
    file_get_contents(
        BASE_PATH
        . '/system/Routing/Router.php'
    );


$web =
    file_get_contents(
        BASE_PATH
        . '/routes/web.php'
    );


$ticketing =
    file_get_contents(
        BASE_PATH
        . '/routes/ticketing-runtime.php'
    );


$provider =
    file_get_contents(
        BASE_PATH
        . '/app/Services/UiContent/'
        . 'ScopeProviders/'
        . 'TicketingUiContentScopeProvider.php'
    );


$seed =
    file_get_contents(
        BASE_PATH
        . '/system/Database/Migrations/'
        . 'SeedInitialDynamicUiContent.php'
    );


$assert(
    str_contains(
        (string) $router,
        'function notFound('
    )
    &&
    str_contains(
        (string) $web,
        'T3F_C1_GLOBAL_DYNAMIC_404_V1'
    ),
    'Global 404 integration missing.'
);


$assert(
    str_contains(
        (string) $ticketing,
        'T3F_C1_ATTACHMENT_ERROR_PARENT_AUTHORIZATION_V1'
    )
    &&
    str_contains(
        (string) $ticketing,
        'ticketing.attachment.not_found'
    ),
    'Attachment 404 integration missing.'
);


$auth =
    strpos(
        (string) $ticketing,
        'if ($parentAuthorized)'
    );


$scope =
    strpos(
        (string) $ticketing,
        'TicketAttachmentStorageScopeService()'
    );


$assert(
    $auth !== false
    &&
    $scope !== false
    &&
    $auth < $scope,
    'Fine scope occurs before parent authorization.'
);


$assert(
    str_contains(
        (string) $provider,
        'resolveByHost('
    )
    &&
    str_contains(
        (string) $provider,
        "'project'"
    )
    &&
    str_contains(
        (string) $provider,
        "'portal'"
    ),
    'Project/Portal provider missing.'
);


foreach ([
    'http.404',
    'ticketing.attachment.not_found',
    'INSERT IGNORE',
] as $marker) {

    $assert(
        str_contains(
            (string) $seed,
            $marker
        ),
        'Seed marker missing: '
        . $marker
    );
}


$assert(
    !str_contains(
        (string) $seed,
        'action_url'
    ),
    'Arbitrary action_url introduced.'
);


echo "DYNAMIC_UI_CONTENT_HTTP_PRESENTATION_CONTRACT=PASS\n";
echo "DYNAMIC_MODULE_ROUTE_CONTEXT=PASS\n";
echo "HTTP_404_STATUS_PRESERVED=PASS\n";
echo "SHARED_RTL_ERROR_VIEW=PASS\n";
echo "PARENT_AUTHORIZATION_BEFORE_FINE_SCOPE=PASS\n";
echo "TICKETING_PROJECT_PORTAL_PROVIDER=PASS\n";
echo "SEMANTIC_ACTION_CODES_ONLY=PASS\n";
