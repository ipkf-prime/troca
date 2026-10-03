<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=[
 'repo'=>$root.'/public_html/app/Repositories/WorkExternalSourceBridgeRepository.php',
 'bridge'=>$root.'/public_html/app/Services/Work/WorkExternalSourceBridgeService.php',
 'detail'=>$root.'/public_html/app/Services/Work/WorkItemDetailService.php',
 'work'=>$root.'/public_html/resources/views/admin/work-item-show.php',
 'ticket'=>$root.'/public_html/resources/views/admin/ticketing-ticket-detail.php',
 'ui'=>$root.'/public_html/resources/ui-content/ticket-work-policy-ui.json',
];
foreach($files as $p){if(!is_file($p)){fwrite(STDERR,"MISSING=$p\n");exit(1);}}
$r=file_get_contents($files['repo']);$b=file_get_contents($files['bridge']);$d=file_get_contents($files['detail']);$w=file_get_contents($files['work']);$t=file_get_contents($files['ticket']);
$u=json_decode(file_get_contents($files['ui']),true,512,JSON_THROW_ON_ERROR);
$checks=[
 'assignee'=>str_contains($r,'assignee.display_name_snapshot AS assignee_name'),
 'source_api'=>str_contains($b,'public function sourceLinksForItem('),
 'source_sso'=>str_contains($b,'/auth/module-sso/start?return_path='),
 'detail'=>str_contains($d,"'source_links' =>"),
 'work_panel'=>str_contains($w,'data-work-source-links'),
 'ticket_status'=>str_contains($t,'ticket_detail.item_status'),
 'ticket_assignee'=>str_contains($t,'ticket_detail.item_assignee'),
 'json_source'=>isset($u['work_item_source']['heading']),
 'json_relation'=>isset($u['options']['relation_type_code']['created_from']),
 'no_ticket_write'=>!str_contains($b,'UPDATE ticketing_tickets')&&!str_contains($d,'UPDATE ticketing_tickets'),
];
$fail=[];foreach($checks as $k=>$v){if(!$v)$fail[]=$k;}
if($fail){fwrite(STDERR,'FAILED='.implode(',',$fail).PHP_EOL);exit(1);}
if(preg_match('/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',$r.$b.$d.$w.$t)){fwrite(STDERR,"CUSTOMER_LITERAL=FAIL\n");exit(1);}
echo "TICKET_WORK_LIFECYCLE_VISIBILITY_TEST=PASS\n";
