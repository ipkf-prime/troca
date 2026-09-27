<?php
declare(strict_types=1);

$root=dirname(__DIR__);

$runtime=(string)file_get_contents(
    $root
    .'/public_html/routes/ticketing-runtime.php'
);

$members=(string)file_get_contents(
    $root
    .'/public_html/routes/ticketing-project-membership.php'
);

$catalog=json_decode(
    (string)file_get_contents(
        $root
        .'/public_html/resources/ui-content/'
        .'ticketing-t2-route-dynamic-content.json'
    ),
    true
);

if(!is_array($catalog) || count($catalog)!==8){
    throw new RuntimeException(
        'T2 route dynamic catalog must contain exactly 8 items.'
    );
}

$required=[
    'ticketing.t2.route.dashboard.title',
    'ticketing.t2.route.dashboard.unavailable_prefix',
    'ticketing.t2.route.staff.title',
    'ticketing.t2.route.staff.unavailable_prefix',
    'ticketing.t2.route.members.title',
    'ticketing.t2.route.members.unavailable',
    'ticketing.t2.route.members.project_not_found_title',
    'ticketing.t2.route.members.project_not_found',
];

$keys=[];

foreach($catalog as $item){
    if(
        !is_array($item)
        ||
        trim((string)($item['key']??''))===''
        ||
        trim((string)($item['surface']??''))===''
        ||
        (string)($item['body']??'')===''
    ){
        throw new RuntimeException(
            'Invalid T2 route catalog item.'
        );
    }

    $keys[(string)$item['key']]=true;
}

foreach($required as $key){
    if(!isset($keys[$key])){
        throw new RuntimeException(
            'Missing T2 route content key: '.$key
        );
    }

    if(
        !str_contains($runtime,$key)
        &&
        !str_contains($members,$key)
    ){
        throw new RuntimeException(
            'Route does not consume dynamic key: '.$key
        );
    }
}

foreach([
    "'پشتیبانی و تیکتینگ'",
    "'داشبورد تیکتینگ در حال حاضر '",
    "'کارتابل پشتیبانی'",
    "'کارتابل پشتیبانی در دسترس نیست. '",
] as $forbidden){
    if(str_contains($runtime,$forbidden)){
        throw new RuntimeException(
            'T2 runtime route literal remains: '.$forbidden
        );
    }
}

foreach([
    "'اعضا و دسترسی‌ها'",
    "'اطلاعات اعضا و دسترسی‌های پروژه در حال حاضر در دسترس نیست.'",
    "'پروژه پیدا نشد'",
    "'پروژه پشتیبانی موردنظر پیدا نشد.'",
] as $forbidden){
    if(str_contains($members,$forbidden)){
        throw new RuntimeException(
            'T2 member route literal remains: '.$forbidden
        );
    }
}

foreach([
    'CORC',
    'NP-',
    'نپ',
    'نهاده پخش',
] as $forbidden){
    if(
        str_contains(
            (string)file_get_contents(
                $root
                .'/public_html/resources/ui-content/'
                .'ticketing-t2-route-dynamic-content.json'
            ),
            $forbidden
        )
    ){
        throw new RuntimeException(
            'Business hardcode in route catalog: '.$forbidden
        );
    }
}

echo "T2_ROUTE_DYNAMIC_CONTENT_CLOSURE_TEST=PASS\n";
echo "T2_ROUTE_DYNAMIC_KEYS=8\n";
echo "T2_ROUTE_VISIBLE_TARGET_HARDCODE=0\n";
echo "BUSINESS_HARDCODE=0\n";
