<?php

$root = dirname(__DIR__);
$env = file_get_contents($root . '/public_html/system/Support/Env.php');
$bootstrap = file_get_contents($root . '/public_html/bootstrap/app.php');
$urls = file_get_contents($root . '/public_html/system/Support/ApplicationUrlRegistry.php');
$catalog = file_get_contents($root . '/public_html/app/Services/ApplicationModuleRegistryService.php');
$deploy = file_get_contents($root . '/.cpanel.yml');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(str_contains($bootstrap, 'Env::loadLayered'), 'Bootstrap must load layered configuration.');
$expect(str_contains($env, 'IPKF_SHARED_ENV') && str_contains($env, 'IPKF_MODULE'), 'Shared path and stable module identity are required.');
$expect(str_contains($urls, "moduleUrl('work', 'WORK_APP_URL'"), 'Work URL must be environment driven.');
$expect(str_contains($urls, '$this->workHost()'), 'Work host must participate in the allowed-host registry.');
$expect(!str_contains($catalog, 'work-dev.troca.ir') && !str_contains($catalog, 'oa-dev.troca.ir'), 'Runtime catalog must not contain deployment domains.');
$expect(str_contains($deploy, 'ipkf-deploy.env') && str_contains($deploy, 'IPKF_WORK_DEPLOYPATH'), 'Deployment destinations must come from the server registry.');
$expect(!str_contains($deploy, 'dev.troca.ir') && !str_contains($deploy, 'oa-dev.troca.ir') && !str_contains($deploy, 'work-dev.troca.ir'), 'Deployment manifest must not contain domain-based paths.');

echo "Layered module configuration checks passed.\n";
