#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"

cd "$repo_root"

current_branch="$(git branch --show-current)"

if [[ "$current_branch" != "$expected_branch" ]]; then
    printf 'Expected branch %s; current branch is %s.\n' \
        "$expected_branch" "$current_branch" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Working tree or index is not clean. Patch stopped." >&2
    git status --short --branch >&2
    exit 1
fi

middleware_file="public_html/system/Http/Middleware/CsrfMiddleware.php"
service_file="public_html/app/Services/NotificationBaleEnrollmentService.php"
routes_file="public_html/routes/communication-center.php"
test_file="tests/SignedBaleWebhookCsrfExemptionTest.php"
repo_tool_file="tools/apply-signed-bale-webhook-csrf-exemption-v061-r4.sh"

for file in \
  "$middleware_file" \
  "$service_file" \
  "$routes_file"
do
    if [[ ! -f "$file" ]]; then
        printf 'Required file not found: %s\n' "$file" >&2
        exit 1
    fi
done

cleanup_on_error() {
    status=$?

    if [[ "$status" -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE" >&2

        git restore --staged --worktree -- \
          "$middleware_file" \
          "$service_file" \
          "$routes_file" \
          >/dev/null 2>&1 || true

        rm -f -- \
          "$test_file" \
          "$repo_tool_file"
    fi

    exit "$status"
}

trap cleanup_on_error EXIT

echo
echo "=== Apply Signed Bale Webhook CSRF Exemption R4 ==="

cat > "$middleware_file" <<'PHP'
<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Security\Csrf;

class CsrfMiddleware
{
    public function handle(
        Request $request,
        Response $response,
        callable $next
    ): Response {
        if ($this->isExempt($request)) {
            return $next($request, $response);
        }

        if (in_array(
            $request->method(),
            ['POST', 'PUT', 'DELETE'],
            true
        )) {
            $token =
                $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $request->input('_token', '');

            $csrf = new Csrf();

            if (!$csrf->check($token)) {
                return $response->status(419)->json([
                    'status' => 'error',
                    'message' => 'CSRF Token Mismatch',
                ]);
            }
        }

        return $next($request, $response);
    }

    private function isExempt(Request $request): bool
    {
        return strtoupper($request->method()) === 'POST'
            && preg_match(
                '#^/webhooks/notifications/bale/'
                . 'npi_[a-f0-9]{24}/'
                . '[a-f0-9]{64}/?$#D',
                $request->uri()
            ) === 1;
    }
}
PHP

echo "UPDATED: exact signed webhook CSRF middleware"

SERVICE_FILE="$service_file" \
ROUTES_FILE="$routes_file" \
perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

sub read_lines {
    my ($path) = @_;

    open my $fh, '<:encoding(UTF-8)', $path
        or die "Could not read $path: $!\n";

    my @lines = <$fh>;

    close $fh;

    for my $line (@lines) {
        $line =~ s/\r\n?/\n/g;
    }

    return @lines;
}

sub write_lines {
    my ($path, @lines) = @_;

    open my $fh, '>:encoding(UTF-8)', $path
        or die "Could not write $path: $!\n";

    print {$fh} @lines;

    close $fh;
}

my $service_path = $ENV{SERVICE_FILE};
my @service = read_lines($service_path);
my @service_out;

my $signature_inserted = 0;
my $provider_error_seen = 0;
my $validation_inserted = 0;
my $helper_inserted = 0;

my @validation = (
    "\n",
    "        \$expectedSignature =\n",
    "            \$this->webhookSignature(\$provider);\n",
    "\n",
    "        if (\n",
    "            preg_match(\n",
    "                '/^[a-f0-9]{64}\$/',\n",
    "                \$signature\n",
    "            ) !== 1\n",
    "            || !hash_equals(\n",
    "                \$expectedSignature,\n",
    "                \$signature\n",
    "            )\n",
    "        ) {\n",
    "            throw new RuntimeException(\n",
    "                'notification_bale_webhook_signature_invalid'\n",
    "            );\n",
    "        }\n",
);

my @helper = (
    "    private function webhookSignature(\n",
    "        array \$provider\n",
    "    ): string {\n",
    "        \$secrets =\n",
    "            \$this->runtime->secrets(\$provider);\n",
    "        \$botToken = trim((string) (\n",
    "            \$secrets['bot_token'] ?? ''\n",
    "        ));\n",
    "        \$providerReference = trim((string) (\n",
    "            \$provider['public_reference'] ?? ''\n",
    "        ));\n",
    "\n",
    "        if (\n",
    "            \$botToken === ''\n",
    "            || preg_match(\n",
    "                '/^npi_[a-f0-9]{24}\$/',\n",
    "                \$providerReference\n",
    "            ) !== 1\n",
    "        ) {\n",
    "            throw new RuntimeException(\n",
    "                'notification_gateway_secret_unavailable'\n",
    "            );\n",
    "        }\n",
    "\n",
    "        return hash_hmac(\n",
    "            'sha256',\n",
    "            \$providerReference,\n",
    "            \$botToken\n",
    "        );\n",
    "    }\n",
    "\n",
);

for my $index (0 .. $#service) {
    my $line = $service[$index];

    push @service_out, $line;

    if (
        !$signature_inserted
        && $line eq "        string \$providerReference,\n"
        && $index > 0
        && $service[$index - 1]
            eq "    public function handleWebhook(\n"
    ) {
        push @service_out,
            "        string \$signature,\n";

        $signature_inserted = 1;
        next;
    }

    if (
        $line =~
            /notification_bale_provider_unavailable/
    ) {
        $provider_error_seen = 1;
    }

    if (
        $provider_error_seen
        && !$validation_inserted
        && $line eq "        }\n"
    ) {
        push @service_out, @validation;

        $validation_inserted = 1;
        $provider_error_seen = 0;
        next;
    }

    if (
        !$helper_inserted
        && $line eq "    private function sendText(\n"
    ) {
        pop @service_out;
        push @service_out, @helper, $line;

        $helper_inserted = 1;
    }
}

die "Webhook signature argument was not inserted.\n"
    if !$signature_inserted;

die "Webhook signature validation was not inserted.\n"
    if !$validation_inserted;

die "Webhook signature helper was not inserted.\n"
    if !$helper_inserted;

write_lines($service_path, @service_out);

print "UPDATED: webhook signature argument\n";
print "UPDATED: constant-time signature validation\n";
print "UPDATED: derived webhook path signature\n";

my $routes_path = $ENV{ROUTES_FILE};
my @routes = read_lines($routes_path);
my @routes_out;

my $route_path_updated = 0;
my $inside_webhook = 0;
my $reference_seen = 0;
my $route_argument_inserted = 0;

my @signature_argument = (
    "                    trim((string) \$request->route(\n",
    "                        'signature',\n",
    "                        ''\n",
    "                    )),\n",
);

for my $line (@routes) {
    if (
        !$route_path_updated
        && $line eq
            "    '/webhooks/notifications/bale/{reference}',\n"
    ) {
        $line =
            "    '/webhooks/notifications/bale/"
            . "{reference}/{signature}',\n";

        $route_path_updated = 1;
        $inside_webhook = 1;
    }

    push @routes_out, $line;

    if (
        $inside_webhook
        && $line eq "                        'reference',\n"
    ) {
        $reference_seen = 1;
        next;
    }

    if (
        $inside_webhook
        && $reference_seen
        && !$route_argument_inserted
        && $line eq "                    )),\n"
    ) {
        push @routes_out, @signature_argument;

        $route_argument_inserted = 1;
        $reference_seen = 0;
        next;
    }

    if (
        $inside_webhook
        && $line eq ");\n"
        && $route_argument_inserted
    ) {
        $inside_webhook = 0;
    }
}

die "Signed webhook route path was not updated.\n"
    if !$route_path_updated;

die "Signed webhook route argument was not inserted.\n"
    if !$route_argument_inserted;

write_lines($routes_path, @routes_out);

print "UPDATED: signed Bale webhook route\n";
PERL

cat > "$test_file" <<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(
            STDERR,
            "FAIL: cannot read {$path}\n"
        );
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$middleware = $read(
    'public_html/system/Http/Middleware/'
    . 'CsrfMiddleware.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);

$expect(
    str_contains(
        $middleware,
        'private function isExempt'
    )
    && str_contains(
        $middleware,
        'npi_[a-f0-9]{24}'
    )
    && str_contains(
        $middleware,
        '[a-f0-9]{64}'
    )
    && str_contains(
        $middleware,
        "strtoupper(\$request->method()) === 'POST'"
    ),
    'Signed Bale webhook CSRF exemption is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/webhooks/notifications/bale/'
        . '{reference}/{signature}'
    )
    && str_contains(
        $routes,
        "'signature'"
    ),
    'Signed Bale webhook route is incomplete.'
);

$expect(
    str_contains(
        $service,
        'string $signature'
    )
    && str_contains(
        $service,
        'private function webhookSignature'
    )
    && str_contains(
        $service,
        'hash_hmac'
    )
    && str_contains(
        $service,
        'hash_equals'
    )
    && str_contains(
        $service,
        'notification_bale_webhook_signature_invalid'
    ),
    'Signed Bale webhook verification is incomplete.'
);

echo "Signed Bale webhook CSRF exemption checks passed.\n";
PHP

echo "ADDED: signed Bale webhook CSRF test"

mkdir -p tools

cp -- "$0" "$repo_tool_file"

echo "ADDED: reproducible signed webhook patch tool"

git add -- \
  "$middleware_file" \
  "$service_file" \
  "$routes_file" \
  "$test_file" \
  "$repo_tool_file"

echo
echo "=== Cached Validation ==="

git diff --cached --check

echo
echo "=== Signed Webhook Markers ==="

git grep -n -E \
  "isExempt|webhookSignature|hash_hmac|hash_equals|notification_bale_webhook_signature_invalid|webhooks/notifications/bale/.+signature" \
  -- \
  "$middleware_file" \
  "$service_file" \
  "$routes_file" \
  "$test_file"

echo
echo "=== Unstaged Changes Check ==="

if git diff --quiet; then
    echo "UNSTAGED_CHANGES=0"
else
    echo "UNSTAGED_CHANGES=1"
    git status --short
    exit 1
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "SIGNED BALE WEBHOOK CSRF EXEMPTION R4 ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
