<?php
declare(strict_types=1);

$root=dirname(__DIR__);

$targets=[
    [
        'path'=>$root
            .'/public_html/resources/views/admin/'
            .'ticketing-staff.php',
        'surface'=>'ticketing-staff',
    ],
    [
        'path'=>$root
            .'/public_html/resources/views/admin/'
            .'ticketing-dashboard.php',
        'surface'=>'ticketing-dashboard',
    ],
    [
        'path'=>$root
            .'/public_html/resources/views/admin/'
            .'ticketing-project-members.php',
        'surface'=>'ticketing-project-members',
    ],
];

$arabic=
    '/[\x{0621}-\x{063A}'
    .'\x{0641}-\x{064A}'
    .'\x{066E}-\x{06D3}'
    .'\x{06FA}-\x{06FC}]/u';

foreach ($targets as $target) {

    $source=file_get_contents(
        (string)$target['path']
    );

    if (!is_string($source)) {
        throw new RuntimeException(
            'Unreadable T2 view: '
            .(string)$target['surface']
        );
    }

    if (
        !str_contains(
            $source,
            'UiContentInlineGuide::bodyText'
        )
        ||
        !str_contains(
            $source,
            "'".(string)$target['surface']."'"
        )
    ) {
        throw new RuntimeException(
            'Dynamic resolver missing: '
            .(string)$target['surface']
        );
    }

    foreach (token_get_all($source) as $token) {

        if (!is_array($token)) {
            continue;
        }

        [$id,$text,$line]=$token;

        if (
            $id===T_COMMENT
            ||
            $id===T_DOC_COMMENT
        ) {
            continue;
        }

        if (
            in_array(
                $id,
                [
                    T_CONSTANT_ENCAPSED_STRING,
                    T_ENCAPSED_AND_WHITESPACE,
                    T_INLINE_HTML,
                ],
                true
            )
            &&
            (
                preg_match($arabic,$text)===1
                ||
                str_contains($text,'—')
            )
        ) {
            $oneLine=preg_replace(
                '/\s+/u',
                ' ',
                trim($text)
            );

            throw new RuntimeException(
                'Hardcoded visible UI text remains: '
                .(string)$target['surface']
                .'|'
                .token_name($id)
                .'|line='
                .$line
                .'|text='
                .$oneLine
            );
        }
    }
}

$servicePath=
    $root
    .'/public_html/app/Services/Ticketing/'
    .'TicketProjectMemberAccessService.php';

$service=file_get_contents($servicePath);

if (!is_string($service)) {
    throw new RuntimeException(
        'Member access service unavailable.'
    );
}

foreach (token_get_all($service) as $token) {
    if (
        is_array($token)
        &&
        in_array(
            $token[0],
            [
                T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE,
            ],
            true
        )
        &&
        preg_match($arabic,$token[1])===1
    ) {
        throw new RuntimeException(
            'Hardcoded role label remains'
            .'|'
            .token_name($token[0])
            .'|line='
            .$token[2]
        );
    }
}

foreach([
    'T2_DYNAMIC_ROLE_TITLES_V1',
    'PROJECT_ROLE_CONTENT_KEYS',
    'STAFF_ROLE_CONTENT_KEYS',
    'UiContentInlineGuide::bodyText',
] as $marker){
    if(!str_contains($service,$marker)){
        throw new RuntimeException(
            'Dynamic role contract missing: '
            .$marker
        );
    }
}

$catalogPath=
    $root
    .'/public_html/resources/ui-content/'
    .'ticketing-t2-dynamic-content.json';

$catalog=json_decode(
    (string)file_get_contents($catalogPath),
    true
);

if (
    !is_array($catalog)
    ||
    count($catalog)<178
) {
    throw new RuntimeException(
        'T2 dynamic catalog incomplete.'
    );
}

$keys=[];

foreach ($catalog as $item) {
    if (
        !is_array($item)
        ||
        trim((string)($item['key']??''))===''
        ||
        trim((string)($item['surface']??''))===''
        ||
        (string)($item['body']??'')===''
    ) {
        throw new RuntimeException(
            'Invalid dynamic catalog item.'
        );
    }

    $key=(string)$item['key'];

    if (isset($keys[$key])) {
        throw new RuntimeException(
            'Duplicate dynamic key: '.$key
        );
    }

    $keys[$key]=true;
}

foreach([
    'ticketing.t2.members.role.project.requester',
    'ticketing.t2.members.role.project.member',
    'ticketing.t2.members.role.project.manager',
    'ticketing.t2.members.role.staff.agent',
    'ticketing.t2.members.role.staff.supervisor',
    'ticketing.t2.members.role.staff.manager',
] as $requiredKey){
    if(!isset($keys[$requiredKey])){
        throw new RuntimeException(
            'Required role content key missing: '
            .$requiredKey
        );
    }
}

$migrationPath=
    $root
    .'/public_html/system/Database/Migrations/'
    .'SeedTicketingPhase1T2DynamicContent.php';

$migration=file_get_contents($migrationPath);

if (!is_string($migration)) {
    throw new RuntimeException(
        'T2 dynamic migration unavailable.'
    );
}

foreach([
    "'guide'",
    "'surface'",
    "'scope:ticketing:surface:'",
    "'ticketing'",
    'INSERT IGNORE INTO',
    'ui_content_definitions',
    'ui_content_overrides',
] as $marker){
    if(!str_contains($migration,$marker)){
        throw new RuntimeException(
            'Dynamic migration contract missing: '
            .$marker
        );
    }
}

if (str_contains($migration,"'static'")) {
    throw new RuntimeException(
        'Static content type is incompatible with UiContentInlineGuide.'
    );
}

foreach([
    'CORC',
    'NP-',
    'نپ',
    'نهاده پخش',
] as $forbidden){
    if(
        str_contains($service,$forbidden)
        ||
        str_contains($migration,$forbidden)
        ||
        str_contains(
            (string)file_get_contents($catalogPath),
            $forbidden
        )
    ){
        throw new RuntimeException(
            'Business hardcode found: '
            .$forbidden
        );
    }
}

echo "T2_DYNAMIC_CONTENT_CLOSURE_TEST=PASS\n";
echo "T2_VISIBLE_RUNTIME_HARDCODE=0\n";
echo "T2_DYNAMIC_ROLE_TITLES=YES\n";
echo "T2_DYNAMIC_RESOLVER_CONTRACT=GUIDE_PLUS_SURFACE\n";
echo "T2_DYNAMIC_CATALOG_COUNT="
    .count($catalog)
    ."\n";
echo "BUSINESS_HARDCODE=0\n";
