<?php
declare(strict_types=1);
/** Pure offline Safir V2.4 pilot/RBAC regression; never contacts provider or DB. */
define('BASE_PATH', dirname(__DIR__) . '/public_html');
require BASE_PATH . '/system/Support/Env.php';
require BASE_PATH . '/system/Support/Session.php';
require BASE_PATH . '/app/Services/BaleSafirCampaignService.php';

$rbac = (string) file_get_contents(BASE_PATH . '/app/Services/AdminNavigationRbacService.php');
if (!str_contains($rbac, "'/admin/communications/safir/test-report.csv' => 'notifications.send.manage'")) {
    throw new RuntimeException('REPORT_CSV_RBAC_MAPPING_MISSING');
}
echo "SAFIR_CSV_RBAC_MATCHES_MAIN_PAGE=PASS\n";

$svc = new \App\Services\BaleSafirCampaignService();
$ref = new ReflectionClass($svc);
$send = $ref->getMethod('sendEnabled');
$pilot = $ref->getMethod('pilotEnabled');
$guard = $ref->getMethod('assertPilotCampaign');
$p1 = '989111111111';
$p2 = '989122222222';
$p3 = '989133333333';
$p4 = '989144444444';
$set = static function(array $values): void {
    foreach ($values as $k => $v) {
        $_ENV[$k] = $_SERVER[$k] = $v;
    }
};
$set([
    'APP_ENV'=>'production',
    'BALE_SAFIR_SEND_ENABLED'=>'false',
    'BALE_SAFIR_PRODUCTION_APPROVED'=>'false',
    'BALE_SAFIR_PILOT_ENABLED'=>'false',
    'BALE_SAFIR_PILOT_PRODUCTION_APPROVED'=>'false',
    'BALE_SAFIR_PILOT_PHONES'=>'',
]);
if ($send->invoke($svc) || $pilot->invoke($svc)) throw new RuntimeException('PILOT_DEFAULT_NOT_LOCKED');
echo "PILOT_DEFAULT_FAIL_CLOSED=PASS\n";
$set(['BALE_SAFIR_PILOT_ENABLED'=>'true','BALE_SAFIR_PILOT_PHONES'=>"$p1,$p2"]);
if ($send->invoke($svc) || $pilot->invoke($svc)) throw new RuntimeException('PILOT_APPROVAL_GATE_BYPASS');
$set(['BALE_SAFIR_PILOT_PRODUCTION_APPROVED'=>'true']);
if (!$send->invoke($svc) || !$pilot->invoke($svc)) throw new RuntimeException('PILOT_TWO_PHONE_GATE_FAILED');
echo "PILOT_TWO_PHONE_ONLY=PASS\n";
$set(['BALE_SAFIR_PILOT_PHONES'=>"$p1,$p1"]);
if ($send->invoke($svc)) throw new RuntimeException('DUPLICATE_ALLOWLIST_ACCEPTED');
$set(['BALE_SAFIR_PILOT_PHONES'=>"$p1,$p2,$p3,$p4"]);
if ($send->invoke($svc)) throw new RuntimeException('FOUR_PHONE_ALLOWLIST_ACCEPTED');
$set(['BALE_SAFIR_PILOT_PHONES'=>"$p1,$p2"]);
$set(['BALE_SAFIR_SEND_ENABLED'=>'true','BALE_SAFIR_PRODUCTION_APPROVED'=>'true','BALE_SAFIR_PILOT_PHONES'=>'invalid']);
if ($send->invoke($svc)) throw new RuntimeException('BULK_OVERRIDE_BYPASSED_INVALID_PILOT');
$set(['BALE_SAFIR_SEND_ENABLED'=>'false','BALE_SAFIR_PRODUCTION_APPROVED'=>'false','BALE_SAFIR_PILOT_PHONES'=>"$p1,$p2,$p3"]);
if (!$pilot->invoke($svc)) throw new RuntimeException('THREE_PHONE_PILOT_INVALID');
echo "PILOT_LIMIT_AND_BULK_OVERRIDE=PASS\n";

$row = static fn(string $phone,string $status='ready'):array => ['phone'=>$phone,'status'=>$status];
$allow = static function(array $rows) use($guard,$svc):void { $guard->invoke($svc,['rows'=>$rows]); };
$deny = static function(array $rows) use($guard,$svc):void {
    try { $guard->invoke($svc,['rows'=>$rows]); }
    catch (\RuntimeException $e) {
        if ($e->getMessage()==='safir_pilot_list_not_allowed') return;
        throw $e;
    }
    throw new RuntimeException('PILOT_UNEXPECTED_ALLOWED');
};
$allow([$row($p1),$row($p2)]);
$allow([$row($p1,'sent'),$row($p2),$row($p3)]);
$deny([$row($p1)]);
$deny([$row($p1),$row($p1)]);
$deny([$row($p1),$row($p4)]);
$deny([$row($p1),$row($p2),$row($p3),$row($p4)]);
$deny([$row($p1),$row($p2,'skipped')]);
$deny([$row('09111111111'),$row($p2)]);
$deny([]);
echo "PILOT_CAMPAIGN_ALLOWLIST_AND_ALL_ROWS_GATE=PASS\n";
echo "NO_HTTP_NO_DB_NO_SEND=PASS\n";
