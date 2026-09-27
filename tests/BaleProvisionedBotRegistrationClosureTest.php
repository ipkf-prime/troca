<?php
declare(strict_types=1);
$root=(string)(getenv('B7_A130_TEST_ROOT') ?: dirname(__DIR__));
$src=$root.'/public_html';
$route=(string)file_get_contents($src.'/routes/bale-menu-management.php');
$rbac=(string)file_get_contents($src.'/app/Services/AdminNavigationRbacService.php');
$service=(string)file_get_contents($src.'/app/Services/BaleMenuManagementService.php');
$register='/admin/communications/settings/bale-menus/register';
$permission='admin.ui_content.manage';
$must=function(bool $ok,string $message): void {if(!$ok){throw new RuntimeException($message);}};
$must(substr_count($rbac,"'{$register}' => '{$permission}',")===1,'Registration route must own exactly one RBAC mapping.');
echo "REGISTER_ROUTE_OWN_RBAC_MAPPING=PASS\n";
$must(substr_count($route,"\$adminGuard(\$response,'{$register}')")===1,'Registration handler must use its own route in adminGuard.');
$must(!str_contains($route,"\$adminGuard(\$response,'/admin/system/help-texts/definition/save')"),'Registration handler still aliases help-text route.');
echo "REGISTER_ADMIN_GUARD_ROUTE_OWNERSHIP=PASS\n";
$permissionPos=strpos($route,"'admin.ui_content.manage'");
$csrfPos=strpos($route,"new \\IPKF\\Security\\Csrf()");
$servicePos=strpos($route,'registerProvisionedBot(');
$must($permissionPos!==false,'Registration permission check missing.');
$must($csrfPos!==false,'Registration CSRF check missing.');
$must($servicePos!==false,'Registration service invocation missing.');
$must($permissionPos<$csrfPos && $csrfPos<$servicePos,'Permission/CSRF/service ordering invalid.');
echo "REGISTER_PERMISSION_CSRF_ORDER=PASS\n";
foreach(['bot_key','site_slug'] as $field){$must(str_contains($route,"input('{$field}'"),"Missing {$field} input.");}
echo "REGISTER_INPUT_CONTRACT=PASS\n";
foreach(['BOT_REGISTRATION_INVALID','BOT_CORE_DEV_REQUIRED','BOT_REGISTRY_UNAVAILABLE','BOT_REGISTRATION_BUSY','BOT_KEY_EXISTS','BOT_REGISTRY_LIMIT','BOT_NOT_PROVISIONED','BOT_NOT_DEV','BOT_ALREADY_REGISTERED','BOT_IDENTITY_CONFLICT','BOT_CATALOG_NOT_READY','BOT_REGISTRY_CHANGED','BOT_AUDIT_UNAVAILABLE','BOT_REGISTRY_VERIFY_FAILED'] as $signal){$must(str_contains($service,$signal),"Missing service safety signal {$signal}.");}
foreach(["flock(\$lock, LOCK_EX | LOCK_NB)","realpath(\$candidate)!==\$candidate","npBaleRuntimeLoad(\$candidate)","npBaleRegistryReadActive(\$runtime['paths']['catalog_dir'])","fwrite(\$audit,\$auditLine)","fflush(\$audit)","fsync(\$audit)","self::saveAtomic(\$registry,\$after)","file_get_contents(\$registry)!==\$after"] as $contract){$must(str_contains($service,$contract),"Missing service contract: {$contract}");}
echo "REGISTER_SERVICE_SAFETY_CONTRACT=PASS\n";
$methodStart=strpos($service,'public function registerProvisionedBot(');
$must($methodStart!==false,'registerProvisionedBot method missing.');
$tail=substr($service,$methodStart);
$nextMethod=strpos($tail,"\n    public function ",20);
$method=$nextMethod===false?$tail:substr($tail,0,$nextMethod);
$must(preg_match('/file_get_contents\s*\([^)]*(token|secret|password|credential)/i',$method)!==1,'Registration method directly reads secret/token content.');
$must(preg_match('/fopen\s*\([^)]*(token|secret|password|credential)/i',$method)!==1,'Registration method directly opens secret/token content.');
echo "REGISTER_DIRECT_SECRET_READ=0\n";
$routeFa=preg_match_all('/[\x{0600}-\x{06FF}]/u',$route);
$must($routeFa===0,'Persian UI literal reintroduced into registration route.');
echo "REGISTER_ROUTE_PERSIAN_LITERAL_COUNT=0\n";
echo "BALE_PROVISIONED_BOT_REGISTRATION_CLOSURE_CONTRACT=PASS\n";
