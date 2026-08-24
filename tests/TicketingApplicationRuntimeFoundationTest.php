<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$urls = $read('public_html/system/Support/ApplicationUrlRegistry.php');
$sso = $read('public_html/app/Services/ModuleSsoService.php');
$rbac = $read('public_html/app/Services/AdminNavigationRbacService.php');

foreach ([
    "moduleUrl('ticketing', 'TICKETING_APP_URL'",
    'function ticketingLaunch',
    'function ticketingHost',
    'function isTicketingHost',
    "\$ticketingPath",
    "\$requestIsTicketing",
    "/admin/ticketing",
] as $needle) {
    $expect(
        str_contains($urls, $needle),
        "ApplicationUrlRegistry missing Ticketing contract: {$needle}"
    );
}

$expect(
    str_contains($sso, "'ticketing' => 'ticketing.ticket.view'"),
    'Module SSO must require the Ticketing view permission.'
);

$expect(
    str_contains($sso, "'ticketing' => \$this->urls->ticketing("),
    'Module SSO must transfer Ticketing tokens to the Ticketing application.'
);

$expect(
    str_contains($sso, "? 'ticketing'"),
    'Module SSO callback must recognize the Ticketing host audience.'
);

$expect(
    str_contains($sso, "str_starts_with(\$parsedPath, '/admin/ticketing/')"),
    'Module SSO must recognize Ticketing return paths.'
);

$expect(
    str_contains($rbac, "'/admin/ticketing' => 'ticketing.ticket.view'"),
    'Ticketing admin route must have RBAC protection.'
);

foreach ([$urls, $sso, $rbac] as $runtime) {
    $expect(
        !str_contains($runtime, 'ticketing-dev.troca.ir'),
        'Ticketing runtime must not hardcode deployment domains.'
    );
}

echo "Ticketing application runtime foundation structural checks passed.\n";
