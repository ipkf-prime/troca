<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$route=(string)file_get_contents($root . '/public_html/routes/bale-menu-management.php');
$view=(string)file_get_contents($root . '/public_html/resources/views/admin/bale-menu-management.php');
$migration=(string)file_get_contents($root . '/public_html/system/Database/Migrations/SeedBaleMenuAdminDynamicContent.php');
$registry=(string)file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
$publicMigrate=(string)file_get_contents($root . '/public_html/public/migrate.php');
$reply=(string)file_get_contents($root . '/public_html/app/Services/BaleTicketReplyService.php');
$dialog=(string)file_get_contents($root . '/public_html/app/Services/BaleDialogContentService.php');

$expect=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};

$expect(preg_match('/[\x{0600}-\x{06FF}]/u',$route)!==1,'Persian literal remains in Bale menu route.');
$expect(preg_match('/[\x{0600}-\x{06FF}]/u',$view)!==1,'Persian literal remains in Bale menu view.');
$expect(!str_contains($view,'placeholder="support-bot"'),'Hard-coded bot-key placeholder remains.');
$expect(!str_contains($view,'placeholder="supportbot-dev.example.ir"'),'Hard-coded site placeholder remains.');

preg_match_all("/core\\.bale-menu\\.(?:ui|guide|notice|error)\\.[a-z0-9_.-]+/",$route . "\n" . $view,$matches);
$references=array_values(array_unique($matches[0]??[]));
sort($references,SORT_STRING);
$expect(count($references)>=30,'Too few Bale dynamic content references.');
$expect(in_array('core.bale-menu.error.key_exists',$references,true),'Existing key_exists UI contract reference missing.');
$missingReferences=[];
foreach($references as $key){
    if(!str_contains($migration,"'key'=>'".$key."'")) $missingReferences[]=$key;
}
$expect(
    $missingReferences===[],
    'Migration seed missing referenced keys: ' . implode(', ', $missingReferences)
);

preg_match_all("/'key'=>'(core\\.bale-menu\\.(?:ui|guide|notice|error)\\.[a-z0-9_.-]+)'/",$migration,$seedMatches);
$seedKeys=array_values(array_unique($seedMatches[1]??[]));
sort($seedKeys,SORT_STRING);
$seedOnly=array_values(array_diff($seedKeys,$references));
$consumerOnly=array_values(array_diff($references,$seedKeys));
$expect($consumerOnly===[],'Consumer keys missing seed: '.implode(', ',$consumerOnly));
$expect($seedOnly===[],'Migration has seed-only keys: '.implode(', ',$seedOnly));
$expect($seedKeys===$references,'Migration seed set must exactly equal runtime consumer set.');

$dialogPattern = <<<'REGEX'
~BaleDialogContentService::text\(\$dialog,\s*['"]([^'"]+)['"]~
REGEX;
preg_match_all($dialogPattern,$reply,$dialogMatches);
$dialogKeys=array_values(array_unique($dialogMatches[1]??[]));
sort($dialogKeys,SORT_STRING);
$expect(count($dialogKeys)===19,'Bot dialog dynamic key count must remain 19.');
for($i=1;$i<=19;$i++){
    $expected='reply.text_' . str_pad((string)$i,2,'0',STR_PAD_LEFT);
    $expect(in_array($expected,$dialogKeys,true),'Missing bot dialog key: '.$expected);
}
$expect(preg_match('/[\x{0600}-\x{06FF}]/u',$reply)!==1,'Persian literal leaked into BaleTicketReplyService.');
$expect(preg_match('/[\x{0600}-\x{06FF}]/u',$dialog)!==1,'Persian literal leaked into BaleDialogContentService.');

$expect(str_contains($registry,'SeedBaleMenuAdminDynamicContent::class'),'Application migration registry marker missing.');
$expect(str_contains($publicMigrate,'new \\IPKF\\Database\\Migrations\\SeedBaleMenuAdminDynamicContent()'),'Public migrate marker missing.');
$expect(str_contains($view,"core.bale-menu.ui.page_title"),'Dynamic page title missing.');
$expect(str_contains($route,"core.bale-menu.ui.page_title"),'Dynamic route title missing.');
$expect(!str_contains($view,'$bmNoticeText=(string)$status'),'Raw technical status fallback remains.');

$seedPersian=preg_match_all('/[\x{0600}-\x{06FF}]/u',$migration,$unused);
$expect($seedPersian>0,'Migration must contain initial Persian seed defaults.');

echo "BALE_MENU_DYNAMIC_CONTENT_CONTRACT=PASS\n";
echo "DYNAMIC_REFERENCE_COVERAGE=PASS\n";
echo 'MIGRATION_SEED_COUNT=' . count($seedKeys) . "\n";
echo "SEED_ONLY_COUNT=0\n";
echo "EXACT_SEED_CONSUMER_PARITY=PASS\n";
echo 'DYNAMIC_REFERENCE_COUNT=' . count($references) . "\n";
echo "ADMIN_ROUTE_VIEW_PERSIAN_LITERAL_COUNT=0\n";
echo "BOT_DIALOG_KEY_COUNT=19\n";
echo "MIGRATION_DEFAULTS_ARE_SEED_ONLY=PASS\n";
