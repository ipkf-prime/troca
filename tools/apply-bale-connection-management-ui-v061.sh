#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="3fc11be"

cd "$repo_root"

current_branch="$(git branch --show-current)"
current_head="$(git rev-parse --short HEAD)"

if [[ "$current_branch" != "$expected_branch" ]]; then
    printf 'Expected branch %s; current branch is %s.\n' \
        "$expected_branch" "$current_branch" >&2
    exit 1
fi

if [[ "$current_head" != "$expected_head" ]]; then
    printf 'Expected HEAD %s; current HEAD is %s.\n' \
        "$expected_head" "$current_head" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Working tree or index is not clean. Patch stopped." >&2
    git status --short --branch >&2
    exit 1
fi

repository_file="public_html/app/Repositories/NotificationMessengerEnrollmentRepository.php"
enrollment_service_file="public_html/app/Services/NotificationBaleEnrollmentService.php"
management_service_file="public_html/app/Services/NotificationBaleConnectionManagementService.php"
settings_service_file="public_html/app/Services/CommunicationSettingsService.php"
routes_file="public_html/routes/communication-center.php"
view_file="public_html/resources/views/admin/communication-settings.php"
style_file="public_html/resources/views/admin/partials/communication-style.php"
test_file="tests/BaleConnectionManagementUiTest.php"
tool_file="tools/apply-bale-connection-management-ui-v061.sh"

required_files=(
  "$repository_file"
  "$enrollment_service_file"
  "$settings_service_file"
  "$routes_file"
  "$view_file"
  "$style_file"
)

for file in "${required_files[@]}"; do
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
          "$repository_file" \
          "$enrollment_service_file" \
          "$settings_service_file" \
          "$routes_file" \
          "$view_file" \
          "$style_file" \
          >/dev/null 2>&1 || true

        rm -f -- \
          "$management_service_file" \
          "$test_file" \
          "$tool_file"
    fi

    exit "$status"
}

trap cleanup_on_error EXIT

echo
echo "=== Add Bale Connection Management UI ==="

cat > "$management_service_file" <<'PHP'
<?php

namespace App\Services;

use App\Repositories\NotificationMessengerEnrollmentRepository;
use App\Repositories\NotificationSendCenterRepository;
use InvalidArgumentException;
use RuntimeException;

class NotificationBaleConnectionManagementService extends BaseService
{
    public function __construct(
        private ?NotificationMessengerEnrollmentRepository $repository = null,
        private ?NotificationSendCenterRepository $recipients = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationMessengerEnrollmentRepository();
        $this->recipients ??=
            new NotificationSendCenterRepository();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function page(int $actorUserId): array
    {
        $this->authorize($actorUserId);

        $providers =
            $this->repository
                ->membershipAuthBaleProviders();

        $providerState = match (count($providers)) {
            0 => 'unconfigured',
            1 => 'ready',
            default => 'ambiguous',
        };

        $provider = $providerState === 'ready'
            ? $providers[0]
            : null;

        $connections = is_array($provider)
            ? $this->repository->connectionStatuses(
                (int) $provider['id']
            )
            : [];

        $users = [];
        $organizations = [];
        $roles = [];
        $cities = [];

        $summary = [
            'total' => 0,
            'connected' => 0,
            'awaiting' => 0,
            'needs_invitation' => 0,
            'without_mobile' => 0,
        ];

        foreach (
            $this->recipients->recipientOptions()
            as $recipient
        ) {
            $userId = (int) ($recipient['id'] ?? 0);
            $status = $this->statusDetails(
                $connections[$userId] ?? []
            );
            $hasMobile = !empty(
                $recipient['has_sms']
            );
            $canInvite =
                $providerState === 'ready'
                && $hasMobile
                && $status['code'] !== 'connected';

            $recipient['bale_status_code'] =
                $status['code'];
            $recipient['bale_activity_at'] =
                $status['activity_at'];
            $recipient['bale_enrollment_expires_at'] =
                $status['expires_at'];
            $recipient['bale_username'] =
                $status['username'];
            $recipient['has_mobile'] = $hasMobile;
            $recipient['can_invite_bale'] =
                $canInvite;
            $recipient['can_disconnect_bale'] =
                $providerState === 'ready'
                && $status['code'] === 'connected';

            $users[] = $recipient;
            $summary['total']++;

            if ($status['code'] === 'connected') {
                $summary['connected']++;
            } elseif (in_array(
                $status['code'],
                ['invited', 'waiting_confirmation'],
                true
            )) {
                $summary['awaiting']++;
            } else {
                $summary['needs_invitation']++;
            }

            if (!$hasMobile) {
                $summary['without_mobile']++;
            }

            $organization = trim((string) (
                $recipient['organization_title'] ?? ''
            ));

            if ($organization !== '') {
                $organizations[$organization] =
                    $organization;
            }

            $city = trim((string) (
                $recipient['city_title'] ?? ''
            ));

            if ($city !== '') {
                $cities[$city] = $city;
            }

            foreach (
                preg_split(
                    '/\s*،\s*/u',
                    (string) (
                        $recipient['role_titles'] ?? ''
                    ),
                    -1,
                    PREG_SPLIT_NO_EMPTY
                ) ?: []
                as $role
            ) {
                $roles[$role] = $role;
            }
        }

        ksort($organizations, SORT_NATURAL);
        ksort($roles, SORT_NATURAL);
        ksort($cities, SORT_NATURAL);

        $providerView = [];

        if (is_array($provider)) {
            $configuration = json_decode(
                (string) (
                    $provider['configuration_json'] ?? ''
                ),
                true
            );

            $providerView = [
                'id' => (int) $provider['id'],
                'public_reference' => (string) (
                    $provider['public_reference'] ?? ''
                ),
                'title' => (string) (
                    $provider['title'] ?? ''
                ),
                'username' => ltrim(trim((string) (
                    is_array($configuration)
                        ? (
                            $configuration[
                                'bot_username'
                            ] ?? ''
                        )
                        : ''
                )), '@'),
            ];
        }

        return [
            'provider_state' => $providerState,
            'provider' => $providerView,
            'summary' => $summary,
            'users' => $users,
            'organizations' =>
                array_values($organizations),
            'roles' => array_values($roles),
            'cities' => array_values($cities),
        ];
    }

    public function disconnect(
        int $actorUserId,
        int $userId
    ): void {
        $this->authorize($actorUserId);

        if ($userId < 1) {
            throw new InvalidArgumentException(
                'notification_bale_connection_not_found'
            );
        }

        $provider = $this->requiredProvider();

        if (!$this->repository->revokeBinding(
            (int) $provider['id'],
            $userId
        )) {
            throw new RuntimeException(
                'notification_bale_connection_not_found'
            );
        }
    }

    private function requiredProvider(): array
    {
        $providers =
            $this->repository
                ->membershipAuthBaleProviders();

        if ($providers === []) {
            throw new RuntimeException(
                'notification_bale_auth_provider_unconfigured'
            );
        }

        if (count($providers) > 1) {
            throw new RuntimeException(
                'notification_bale_auth_provider_ambiguous'
            );
        }

        return $providers[0];
    }

    private function statusDetails(
        array $connection
    ): array {
        $binding = is_array(
            $connection['binding'] ?? null
        ) ? $connection['binding'] : [];

        if ($binding !== []) {
            return [
                'code' => 'connected',
                'activity_at' =>
                    $binding['last_activity_at']
                    ?? $binding['verified_at']
                    ?? '',
                'expires_at' => '',
                'username' => (string) (
                    $binding['username'] ?? ''
                ),
            ];
        }

        $enrollment = is_array(
            $connection['enrollment'] ?? null
        ) ? $connection['enrollment'] : [];

        if ($enrollment === []) {
            return [
                'code' => 'not_connected',
                'activity_at' => '',
                'expires_at' => '',
                'username' => '',
            ];
        }

        $status = (string) (
            $enrollment['status_code'] ?? ''
        );
        $expiresAt = (string) (
            $enrollment['expires_at'] ?? ''
        );
        $expiresTimestamp = $expiresAt !== ''
            ? strtotime($expiresAt)
            : false;

        if (
            in_array(
                $status,
                ['pending', 'started'],
                true
            )
            && $expiresTimestamp !== false
            && $expiresTimestamp <= time()
        ) {
            $status = 'expired';
        }

        $code = match ($status) {
            'pending' => 'invited',
            'started' => 'waiting_confirmation',
            'failed' => 'failed',
            'expired' => 'expired',
            'verified' => 'disconnected',
            default => 'not_connected',
        };

        return [
            'code' => $code,
            'activity_at' =>
                $enrollment['verified_at']
                ?? $enrollment['started_at']
                ?? $enrollment['updated_at']
                ?? $enrollment['created_at']
                ?? '',
            'expires_at' => $expiresAt,
            'username' => '',
        ];
    }

    private function authorize(
        int $actorUserId
    ): void {
        if (
            $actorUserId < 1
            || !$this->authorization->hasPermission(
                $actorUserId,
                'notifications.send.manage'
            )
        ) {
            throw new RuntimeException(
                'notification_send_forbidden'
            );
        }
    }
}
PHP

echo "ADDED: NotificationBaleConnectionManagementService.php"

REPOSITORY_FILE="$repository_file" \
ENROLLMENT_SERVICE_FILE="$enrollment_service_file" \
SETTINGS_SERVICE_FILE="$settings_service_file" \
VIEW_FILE="$view_file" \
ROUTES_FILE="$routes_file" \
perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

sub read_text {
    my ($path) = @_;

    open my $fh, '<:encoding(UTF-8)', $path
        or die "Could not read $path: $!\n";

    local $/;
    my $text = <$fh>;

    close $fh;

    $text =~ s/\r\n?/\n/g;

    return $text;
}

sub write_text {
    my ($path, $text) = @_;

    $text =~ s/\r\n?/\n/g;
    $text =~ s/\n*\z/\n/;

    open my $fh, '>:encoding(UTF-8)', $path
        or die "Could not write $path: $!\n";

    print {$fh} $text;
    close $fh;
}

sub replace_once {
    my ($ref, $old, $new, $label) = @_;

    my $count = () = $$ref =~ /\Q$old\E/g;

    die "Expected one anchor for $label; found $count.\n"
        if $count != 1;

    my $position = index($$ref, $old);

    substr(
        $$ref,
        $position,
        length($old),
        $new
    );

    print "UPDATED: $label\n";
}

my $repository_path = $ENV{REPOSITORY_FILE};
my $repository = read_text($repository_path);

replace_once(
    \$repository,
    <<'OLD_MOBILE_SELECT',
                ) AS mobile
            FROM users
OLD_MOBILE_SELECT
    <<'NEW_MOBILE_SELECT',
                ) AS mobile,
                EXISTS(
                    SELECT 1
                    FROM notification_messenger_bindings
                        AS active_binding
                    WHERE active_binding.user_id =
                        users.id
                      AND active_binding.provider_instance_id = ?
                      AND active_binding.status_code =
                        'active'
                ) AS has_active_binding
            FROM users
NEW_MOBILE_SELECT
    'active Bale binding detection for invitations'
);

replace_once(
    \$repository,
    <<'OLD_MOBILE_EXECUTE',
        $statement->execute($userIds);

        return $statement->fetchAll(
OLD_MOBILE_EXECUTE
    <<'NEW_MOBILE_EXECUTE',
        $providers =
            $this->membershipAuthBaleProviders();
        $providerId = count($providers) === 1
            ? (int) $providers[0]['id']
            : 0;

        $statement->execute(array_merge(
            [$providerId],
            $userIds
        ));

        return $statement->fetchAll(
NEW_MOBILE_EXECUTE
    'invitation query provider binding parameter'
);

my $repository_methods = <<'REPOSITORY_METHODS';
    public function connectionStatuses(
        int $providerInstanceId
    ): array {
        if ($providerInstanceId < 1) {
            return [];
        }

        $statuses = [];

        $bindings = $this->connection()->prepare("
            SELECT
                id,
                public_reference,
                user_id,
                external_user_id,
                chat_id,
                username,
                display_name,
                verified_at,
                last_activity_at
            FROM notification_messenger_bindings
            WHERE provider_instance_id = ?
              AND status_code = 'active'
            ORDER BY
                verified_at DESC,
                id DESC
        ");
        $bindings->execute([$providerInstanceId]);

        foreach (
            $bindings->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $binding
        ) {
            $userId = (int) (
                $binding['user_id'] ?? 0
            );

            if (
                $userId > 0
                && !isset($statuses[$userId]['binding'])
            ) {
                $statuses[$userId]['binding'] =
                    $binding;
            }
        }

        $enrollments = $this->connection()->prepare("
            SELECT enrollments.*
            FROM notification_messenger_enrollments
                AS enrollments
            INNER JOIN (
                SELECT
                    user_id,
                    MAX(id) AS latest_id
                FROM notification_messenger_enrollments
                WHERE provider_instance_id = ?
                GROUP BY user_id
            ) AS latest
              ON latest.latest_id = enrollments.id
            WHERE enrollments.provider_instance_id = ?
            ORDER BY enrollments.id DESC
        ");
        $enrollments->execute([
            $providerInstanceId,
            $providerInstanceId,
        ]);

        foreach (
            $enrollments->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $enrollment
        ) {
            $userId = (int) (
                $enrollment['user_id'] ?? 0
            );

            if ($userId > 0) {
                $statuses[$userId]['enrollment'] =
                    $enrollment;
            }
        }

        return $statuses;
    }

    public function revokeBinding(
        int $providerInstanceId,
        int $userId
    ): bool {
        if (
            $providerInstanceId < 1
            || $userId < 1
        ) {
            return false;
        }

        $statement = $this->connection()->prepare("
            UPDATE notification_messenger_bindings
            SET status_code = 'revoked',
                revoked_at = COALESCE(
                    revoked_at,
                    CURRENT_TIMESTAMP
                ),
                updated_at = CURRENT_TIMESTAMP
            WHERE provider_instance_id = ?
              AND user_id = ?
              AND status_code = 'active'
        ");
        $statement->execute([
            $providerInstanceId,
            $userId,
        ]);

        return $statement->rowCount() > 0;
    }

REPOSITORY_METHODS

replace_once(
    \$repository,
    "    private function isMembershipAuthProvider(\n",
    $repository_methods
        . "    private function isMembershipAuthProvider(\n",
    'Bale connection status and revoke repository methods'
);

write_text($repository_path, $repository);

my $enrollment_service_path =
    $ENV{ENROLLMENT_SERVICE_FILE};
my $enrollment_service =
    read_text($enrollment_service_path);

my $already_connected_guard = <<'ALREADY_CONNECTED';
            if (!empty(
                $recipient['has_active_binding']
            )) {
                $failed++;
                $items[] = [
                    'user_id' =>
                        (int) $recipient['id'],
                    'title' =>
                        (string) $recipient['title'],
                    'status_code' => 'skipped',
                    'error_code' =>
                        'recipient_already_connected',
                ];
                continue;
            }

ALREADY_CONNECTED

replace_once(
    \$enrollment_service,
    "            \$token = bin2hex(random_bytes(24));\n",
    $already_connected_guard
        . "            \$token = bin2hex(random_bytes(24));\n",
    'duplicate Bale invitation guard'
);

write_text(
    $enrollment_service_path,
    $enrollment_service
);

my $settings_service_path =
    $ENV{SETTINGS_SERVICE_FILE};
my $settings_service =
    read_text($settings_service_path);

replace_once(
    \$settings_service,
    <<'OLD_SEND_SECTION',
        'send' => [
OLD_SEND_SECTION
    <<'NEW_SEND_SECTION',
        'bale_connections' => [
            'title' => 'اتصال کاربران به بله',
            'permission' => 'notifications.send.manage',
        ],
        'send' => [
NEW_SEND_SECTION
    'Bale connection settings section'
);

replace_once(
    \$settings_service,
    <<'OLD_CONSTRUCTOR_DEPENDENCIES',
        private ?NotificationDeliveryReportService $deliveryReports = null,
        private ?NotificationSendCenterService $sendCenter = null
OLD_CONSTRUCTOR_DEPENDENCIES
    <<'NEW_CONSTRUCTOR_DEPENDENCIES',
        private ?NotificationDeliveryReportService $deliveryReports = null,
        private ?NotificationSendCenterService $sendCenter = null,
        private ?NotificationBaleConnectionManagementService $baleConnections = null
NEW_CONSTRUCTOR_DEPENDENCIES
    'Bale connection service dependency'
);

replace_once(
    \$settings_service,
    <<'OLD_CONSTRUCTOR_SETUP',
        $this->sendCenter ??= new NotificationSendCenterService();
OLD_CONSTRUCTOR_SETUP
    <<'NEW_CONSTRUCTOR_SETUP',
        $this->sendCenter ??= new NotificationSendCenterService();
        $this->baleConnections ??=
            new NotificationBaleConnectionManagementService();
NEW_CONSTRUCTOR_SETUP
    'Bale connection service initialization'
);

replace_once(
    \$settings_service,
    <<'OLD_PAGE_VARIABLES',
        $deliveryReport = [];
        $notificationSendCenter = [];
OLD_PAGE_VARIABLES
    <<'NEW_PAGE_VARIABLES',
        $deliveryReport = [];
        $notificationSendCenter = [];
        $baleConnectionManagement = [];
NEW_PAGE_VARIABLES
    'Bale connection page data variable'
);

replace_once(
    \$settings_service,
    <<'OLD_SEND_PAGE_LOAD',
        if (
            $section === 'send'
            && isset($sections['send'])
        ) {
OLD_SEND_PAGE_LOAD
    <<'NEW_SEND_PAGE_LOAD',
        if (
            $section === 'bale_connections'
            && isset($sections['bale_connections'])
        ) {
            $baleConnectionManagement =
                $this->baleConnections->page($userId);
        }

        if (
            $section === 'send'
            && isset($sections['send'])
        ) {
NEW_SEND_PAGE_LOAD
    'Bale connection page loading'
);

replace_once(
    \$settings_service,
    <<'OLD_RETURN_SEND_CENTER',
            'notification_send_center' =>
                $notificationSendCenter,
            'delivery_report' => $deliveryReport,
OLD_RETURN_SEND_CENTER
    <<'NEW_RETURN_SEND_CENTER',
            'notification_send_center' =>
                $notificationSendCenter,
            'bale_connection_management' =>
                $baleConnectionManagement,
            'delivery_report' => $deliveryReport,
NEW_RETURN_SEND_CENTER
    'Bale connection page response'
);

write_text(
    $settings_service_path,
    $settings_service
);

my $view_path = $ENV{VIEW_FILE};
my $view = read_text($view_path);

replace_once(
    \$view,
    <<'OLD_VIEW_SEND_DATA',
$notificationSendCenter = is_array(
    $page['notification_send_center'] ?? null
) ? $page['notification_send_center'] : [];
OLD_VIEW_SEND_DATA
    <<'NEW_VIEW_SEND_DATA',
$notificationSendCenter = is_array(
    $page['notification_send_center'] ?? null
) ? $page['notification_send_center'] : [];
$baleConnectionManagement = is_array(
    $page['bale_connection_management'] ?? null
) ? $page['bale_connection_management'] : [];
NEW_VIEW_SEND_DATA
    'Bale connection view data'
);

replace_once(
    \$view,
    <<'OLD_STATUS_MESSAGES',
    'notification_bale_invitation_failed' => ['error', 'ارسال دعوت فعال‌سازی بله انجام نشد.'],
OLD_STATUS_MESSAGES
    <<'NEW_STATUS_MESSAGES',
    'notification_bale_invitation_failed' => ['error', 'ارسال دعوت فعال‌سازی بله انجام نشد.'],
    'notification_bale_connection_disconnected' => ['success', 'اتصال کاربر به بله با موفقیت قطع شد.'],
    'notification_bale_connection_not_found' => ['error', 'اتصال فعال بله برای این کاربر پیدا نشد.'],
    'notification_bale_connection_disconnect_failed' => ['error', 'قطع اتصال کاربر به بله انجام نشد.'],
NEW_STATUS_MESSAGES
    'Bale connection status messages'
);

my $bale_section = <<'BALE_SECTION';
        <?php elseif ($section === 'bale_connections'): ?>
            <?php
            $baleProviderState = (string) (
                $baleConnectionManagement[
                    'provider_state'
                ] ?? 'unconfigured'
            );
            $baleProvider = is_array(
                $baleConnectionManagement[
                    'provider'
                ] ?? null
            ) ? $baleConnectionManagement[
                'provider'
            ] : [];
            $baleSummary = is_array(
                $baleConnectionManagement[
                    'summary'
                ] ?? null
            ) ? $baleConnectionManagement[
                'summary'
            ] : [];
            $baleUsers = is_array(
                $baleConnectionManagement[
                    'users'
                ] ?? null
            ) ? $baleConnectionManagement[
                'users'
            ] : [];
            $baleOrganizations = is_array(
                $baleConnectionManagement[
                    'organizations'
                ] ?? null
            ) ? $baleConnectionManagement[
                'organizations'
            ] : [];
            $baleRoles = is_array(
                $baleConnectionManagement[
                    'roles'
                ] ?? null
            ) ? $baleConnectionManagement[
                'roles'
            ] : [];
            $baleCities = is_array(
                $baleConnectionManagement[
                    'cities'
                ] ?? null
            ) ? $baleConnectionManagement[
                'cities'
            ] : [];
            $baleStatusLabels = [
                'connected' => 'متصل به بله',
                'invited' => 'دعوت ارسال‌شده',
                'waiting_confirmation' =>
                    'در انتظار اشتراک شماره',
                'expired' => 'دعوت منقضی‌شده',
                'failed' => 'ارسال دعوت ناموفق',
                'disconnected' => 'اتصال قطع‌شده',
                'not_connected' => 'متصل نیست',
            ];
            $baleProviderStateLabels = [
                'ready' => [
                    'success',
                    'بات عضویت و احراز هویت آماده است.',
                ],
                'unconfigured' => [
                    'error',
                    'بات بله با کاربرد «عضویت و احراز هویت» تنظیم نشده است.',
                ],
                'ambiguous' => [
                    'error',
                    'بیش از یک بات برای عضویت و احراز هویت تعیین شده است.',
                ],
            ];
            $baleProviderStateView =
                $baleProviderStateLabels[
                    $baleProviderState
                ] ?? $baleProviderStateLabels[
                    'unconfigured'
                ];
            $baleCsrf =
                (new \IPKF\Security\Csrf())->token();
            ?>

            <section
                class="bale-connection-management"
                data-bale-connection-management
            >
                <!-- bale-connection-management-v061 -->
                <header class="bale-connection-intro">
                    <div>
                        <h3>اتصال کاربران به پیام‌رسان بله</h3>
                        <p class="communication-muted">
                            دعوت اتصال را با پیامک ارسال کنید،
                            وضعیت فعال‌سازی هر کاربر را ببینید و
                            اتصال‌های ثبت‌شده را مدیریت کنید.
                        </p>
                    </div>

                    <div class="bale-connection-provider">
                        <span>بات عضویت و احراز هویت</span>
                        <?php if ($baleProvider !== []): ?>
                            <strong><?= admin_h(
                                $baleProvider['title']
                                ?? 'بات بله'
                            ) ?></strong>
                            <small dir="ltr">
                                <?= admin_h(
                                    trim((string) (
                                        $baleProvider[
                                            'username'
                                        ] ?? ''
                                    )) !== ''
                                        ? '@' . $baleProvider[
                                            'username'
                                        ]
                                        : 'username not set'
                                ) ?>
                            </small>
                        <?php else: ?>
                            <strong>تنظیم نشده</strong>
                        <?php endif; ?>
                        <em
                            class="bale-connection-provider__state bale-connection-provider__state--<?= admin_h(
                                $baleProviderStateView[0]
                            ) ?>"
                        >
                            <?= admin_h(
                                $baleProviderStateView[1]
                            ) ?>
                        </em>
                    </div>
                </header>

                <div class="bale-connection-summary">
                    <?php foreach ([
                        'total' => 'کل کاربران',
                        'connected' => 'متصل به بله',
                        'awaiting' => 'در انتظار تکمیل',
                        'needs_invitation' =>
                            'نیازمند دعوت',
                        'without_mobile' =>
                            'فاقد شماره همراه',
                    ] as $summaryKey => $summaryTitle): ?>
                        <article>
                            <span><?= admin_h(
                                $summaryTitle
                            ) ?></span>
                            <strong><?= admin_h(
                                \App\Support\AdminFormat
                                    ::digits(
                                        (int) (
                                            $baleSummary[
                                                $summaryKey
                                            ] ?? 0
                                        )
                                    )
                            ) ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form
                    class="bale-connection-form"
                    method="post"
                    action="/admin/communications/settings/send/bale-invitations"
                    data-bale-invite-form
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($baleCsrf) ?>"
                    >

                    <section class="bale-connection-filters">
                        <label class="bale-connection-filter-search">
                            <span>جست‌وجو</span>
                            <input
                                type="search"
                                placeholder="نام، نام کاربری، نقش، سازمان، شهر یا وضعیت"
                                autocomplete="off"
                                data-bale-search
                            >
                        </label>

                        <label>
                            <span>وضعیت اتصال</span>
                            <select data-bale-status-filter>
                                <option value="">
                                    همه وضعیت‌ها
                                </option>
                                <?php foreach (
                                    $baleStatusLabels
                                    as $statusCode =>
                                        $statusTitle
                                ): ?>
                                    <option value="<?= admin_h(
                                        $statusCode
                                    ) ?>">
                                        <?= admin_h(
                                            $statusTitle
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>سازمان</span>
                            <select data-bale-organization-filter>
                                <option value="">
                                    همه سازمان‌ها
                                </option>
                                <?php foreach (
                                    $baleOrganizations
                                    as $organization
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $organization,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h(
                                            $organization
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>نقش</span>
                            <select data-bale-role-filter>
                                <option value="">
                                    همه نقش‌ها
                                </option>
                                <?php foreach (
                                    $baleRoles as $role
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $role,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h($role) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>شهر</span>
                            <select data-bale-city-filter>
                                <option value="">
                                    همه شهرها
                                </option>
                                <?php foreach (
                                    $baleCities as $city
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $city,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h($city) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </section>

                    <div class="bale-connection-actions">
                        <button
                            class="admin-button admin-button--soft"
                            type="button"
                            data-bale-select-visible
                        >
                            انتخاب کاربران قابل دعوت
                        </button>
                        <button
                            class="admin-button admin-button--soft"
                            type="button"
                            data-bale-clear-selection
                        >
                            پاک‌کردن انتخاب
                        </button>
                        <span class="communication-muted">
                            <strong
                                data-bale-selected-count
                            >۰</strong>
                            کاربر انتخاب شده
                        </span>
                        <button
                            class="admin-button"
                            type="submit"
                            data-bale-send-invitations
                            <?= $baleProviderState === 'ready'
                                ? ''
                                : 'disabled' ?>
                        >
                            ارسال دعوت اتصال با پیامک
                        </button>
                    </div>

                    <div class="bale-connection-user-list">
                        <?php foreach (
                            $baleUsers as $baleUser
                        ): ?>
                            <?php
                            $baleUserStatus = (string) (
                                $baleUser[
                                    'bale_status_code'
                                ] ?? 'not_connected'
                            );
                            $baleUserStatusTitle =
                                $baleStatusLabels[
                                    $baleUserStatus
                                ] ?? $baleUserStatus;
                            $baleActivity = trim(
                                (string) (
                                    $baleUser[
                                        'bale_activity_at'
                                    ] ?? ''
                                )
                            );
                            $baleActivityTitle =
                                $baleActivity !== ''
                                    ? \App\Support\AdminFormat
                                        ::jalaliDateTime(
                                            $baleActivity
                                        )
                                    : '';
                            $baleSearch = mb_strtolower(
                                implode(' ', [
                                    $baleUser['title'] ?? '',
                                    $baleUser[
                                        'username'
                                    ] ?? '',
                                    $baleUser[
                                        'organization_title'
                                    ] ?? '',
                                    $baleUser[
                                        'role_titles'
                                    ] ?? '',
                                    $baleUser[
                                        'city_title'
                                    ] ?? '',
                                    $baleUserStatusTitle,
                                ]),
                                'UTF-8'
                            );
                            ?>
                            <article
                                class="bale-connection-user"
                                data-bale-user
                                data-search="<?= admin_h(
                                    $baleSearch
                                ) ?>"
                                data-status="<?= admin_h(
                                    $baleUserStatus
                                ) ?>"
                                data-organization="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'organization_title'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-role="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'role_titles'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-city="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'city_title'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-can-invite="<?= !empty(
                                    $baleUser[
                                        'can_invite_bale'
                                    ]
                                ) ? '1' : '0' ?>"
                            >
                                <label class="bale-connection-user__select">
                                    <input
                                        type="checkbox"
                                        name="recipient_user_ids[]"
                                        value="<?= admin_h(
                                            $baleUser['id']
                                        ) ?>"
                                        data-bale-user-checkbox
                                        <?= !empty(
                                            $baleUser[
                                                'can_invite_bale'
                                            ]
                                        ) ? '' : 'disabled' ?>
                                    >
                                </label>

                                <div class="bale-connection-user__identity">
                                    <strong><?= admin_h(
                                        $baleUser['title']
                                        ?? 'کاربر'
                                    ) ?></strong>
                                    <small>
                                        <?= admin_h(
                                            implode(
                                                ' • ',
                                                array_filter([
                                                    $baleUser[
                                                        'organization_title'
                                                    ] ?? '',
                                                    $baleUser[
                                                        'role_titles'
                                                    ] ?? '',
                                                    $baleUser[
                                                        'city_title'
                                                    ] ?? '',
                                                ])
                                            )
                                        ) ?>
                                    </small>
                                </div>

                                <div class="bale-connection-user__mobile">
                                    <span>شماره همراه</span>
                                    <strong class="<?= !empty(
                                        $baleUser['has_mobile']
                                    ) ? 'is-ready' : 'is-missing' ?>">
                                        <?= !empty(
                                            $baleUser['has_mobile']
                                        )
                                            ? 'ثبت شده'
                                            : 'ثبت نشده' ?>
                                    </strong>
                                </div>

                                <div class="bale-connection-user__status">
                                    <span
                                        class="bale-connection-status bale-connection-status--<?= admin_h(
                                            $baleUserStatus
                                        ) ?>"
                                    >
                                        <?= admin_h(
                                            $baleUserStatusTitle
                                        ) ?>
                                    </span>
                                    <?php if (
                                        $baleActivityTitle !== ''
                                    ): ?>
                                        <small>
                                            آخرین فعالیت:
                                            <?= admin_h(
                                                $baleActivityTitle
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="bale-connection-user__row-actions">
                                    <?php if (!empty(
                                        $baleUser[
                                            'can_disconnect_bale'
                                        ]
                                    )): ?>
                                        <button
                                            class="admin-button admin-button--soft admin-button--compact"
                                            type="button"
                                            data-bale-disconnect-user="<?= (int) $baleUser['id'] ?>"
                                            data-bale-disconnect-title="<?= admin_h(
                                                $baleUser['title']
                                                ?? 'کاربر'
                                            ) ?>"
                                        >
                                            قطع اتصال
                                        </button>
                                    <?php elseif (!empty(
                                        $baleUser[
                                            'can_invite_bale'
                                        ]
                                    )): ?>
                                        <small>
                                            قابل دعوت با پیامک
                                        </small>
                                    <?php else: ?>
                                        <small>
                                            ابتدا شماره همراه را
                                            ثبت کنید
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </form>

                <form
                    method="post"
                    action="/admin/communications/settings/bale-connections/disconnect"
                    data-bale-disconnect-form
                    hidden
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($baleCsrf) ?>"
                    >
                    <input
                        type="hidden"
                        name="user_id"
                        value=""
                        data-bale-disconnect-user-id
                    >
                </form>

                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-bale-connection-management]'
                    );

                    if (!root) {
                        return;
                    }

                    const users = Array.from(
                        root.querySelectorAll(
                            '[data-bale-user]'
                        )
                    );
                    const search = root.querySelector(
                        '[data-bale-search]'
                    );
                    const status = root.querySelector(
                        '[data-bale-status-filter]'
                    );
                    const organization = root.querySelector(
                        '[data-bale-organization-filter]'
                    );
                    const role = root.querySelector(
                        '[data-bale-role-filter]'
                    );
                    const city = root.querySelector(
                        '[data-bale-city-filter]'
                    );
                    const selectedCount = root.querySelector(
                        '[data-bale-selected-count]'
                    );
                    const submit = root.querySelector(
                        '[data-bale-send-invitations]'
                    );
                    const digits = new Intl.NumberFormat(
                        'fa-IR'
                    );

                    const refreshSelection = () => {
                        const selected = users.filter(
                            (user) => user.querySelector(
                                '[data-bale-user-checkbox]'
                            )?.checked
                        ).length;

                        if (selectedCount) {
                            selectedCount.textContent =
                                digits.format(selected);
                        }

                        if (submit) {
                            submit.disabled =
                                selected < 1
                                || <?= $baleProviderState === 'ready'
                                    ? 'false'
                                    : 'true' ?>;
                        }
                    };

                    const applyFilters = () => {
                        const needle = (
                            search?.value || ''
                        ).trim().toLocaleLowerCase('fa');
                        const wantedStatus =
                            status?.value || '';
                        const wantedOrganization =
                            organization?.value || '';
                        const wantedRole =
                            role?.value || '';
                        const wantedCity =
                            city?.value || '';

                        users.forEach((user) => {
                            user.hidden = !(
                                (
                                    needle === ''
                                    || user.dataset.search
                                        .includes(needle)
                                )
                                && (
                                    wantedStatus === ''
                                    || user.dataset.status
                                        === wantedStatus
                                )
                                && (
                                    wantedOrganization === ''
                                    || user.dataset.organization
                                        === wantedOrganization
                                )
                                && (
                                    wantedRole === ''
                                    || user.dataset.role
                                        .includes(wantedRole)
                                )
                                && (
                                    wantedCity === ''
                                    || user.dataset.city
                                        === wantedCity
                                )
                            );
                        });
                    };

                    search?.addEventListener(
                        'input',
                        applyFilters
                    );
                    status?.addEventListener(
                        'change',
                        applyFilters
                    );
                    organization?.addEventListener(
                        'change',
                        applyFilters
                    );
                    role?.addEventListener(
                        'change',
                        applyFilters
                    );
                    city?.addEventListener(
                        'change',
                        applyFilters
                    );

                    root.querySelector(
                        '[data-bale-select-visible]'
                    )?.addEventListener('click', () => {
                        users
                            .filter(
                                (user) =>
                                    !user.hidden
                                    && user.dataset.canInvite
                                        === '1'
                            )
                            .forEach((user) => {
                                const checkbox =
                                    user.querySelector(
                                        '[data-bale-user-checkbox]'
                                    );

                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });

                        refreshSelection();
                    });

                    root.querySelector(
                        '[data-bale-clear-selection]'
                    )?.addEventListener('click', () => {
                        users.forEach((user) => {
                            const checkbox =
                                user.querySelector(
                                    '[data-bale-user-checkbox]'
                                );

                            if (checkbox) {
                                checkbox.checked = false;
                            }
                        });

                        refreshSelection();
                    });

                    root.addEventListener(
                        'change',
                        (event) => {
                            if (event.target.matches(
                                '[data-bale-user-checkbox]'
                            )) {
                                refreshSelection();
                            }
                        }
                    );

                    const disconnectForm =
                        root.querySelector(
                            '[data-bale-disconnect-form]'
                        );
                    const disconnectUserId =
                        disconnectForm?.querySelector(
                            '[data-bale-disconnect-user-id]'
                        );

                    root.querySelectorAll(
                        '[data-bale-disconnect-user]'
                    ).forEach((button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                const userId =
                                    button.dataset
                                        .baleDisconnectUser
                                    || '';
                                const title =
                                    button.dataset
                                        .baleDisconnectTitle
                                    || 'این کاربر';

                                if (
                                    userId === ''
                                    || !disconnectForm
                                    || !disconnectUserId
                                ) {
                                    return;
                                }

                                if (!window.confirm(
                                    'اتصال بله برای '
                                    + title
                                    + ' قطع شود؟'
                                )) {
                                    return;
                                }

                                disconnectUserId.value =
                                    userId;
                                disconnectForm.submit();
                            }
                        );
                    });

                    applyFilters();
                    refreshSelection();
                })();
                </script>
            </section>

BALE_SECTION

replace_once(
    \$view,
    "        <?php elseif (\$section === 'send'): ?>\n",
    $bale_section
        . "        <?php elseif (\$section === 'send'): ?>\n",
    'dedicated Bale connection management section'
);

my @view_lines = split(/(?<=\n)/, $view);
my @view_out;
my $skipping_invite_button = 0;
my $removed_invite_button = 0;

for my $line (@view_lines) {
    if (
        !$skipping_invite_button
        && $line =~
            /const userActions =/
    ) {
        $skipping_invite_button = 1;
        next;
    }

    if ($skipping_invite_button) {
        if (
            $line =~
                /userActions\?\.\s*append\(inviteButton\);/
        ) {
            $skipping_invite_button = 0;
            $removed_invite_button = 1;
        }

        next;
    }

    push @view_out, $line;
}

die "Could not remove legacy invitation button injection.\n"
    if !$removed_invite_button;

$view = join('', @view_out);

print "UPDATED: removed invitation button from send wizard\n";

write_text($view_path, $view);

my $routes_path = $ENV{ROUTES_FILE};
my $routes = read_text($routes_path);

my $invite_anchor =
    "\$router->post(\n"
    . "    '/admin/communications/settings/send/bale-invitations',";
my $webhook_anchor =
    "\$router->post(\n"
    . "    '/webhooks/notifications/bale/{reference}/{signature}',";

my $invite_start = index(
    $routes,
    $invite_anchor
);
my $invite_end = index(
    $routes,
    $webhook_anchor,
    $invite_start
);

die "Invitation route block was not found.\n"
    if $invite_start < 0 || $invite_end < 0;

my $invite_block = substr(
    $routes,
    $invite_start,
    $invite_end - $invite_start
);

my $redirect_count = (
    $invite_block =~
        s#\?section=send#?section=bale_connections#g
);

die "Expected invitation redirects to change; found $redirect_count.\n"
    if $redirect_count < 2;

substr(
    $routes,
    $invite_start,
    $invite_end - $invite_start,
    $invite_block
);

my $disconnect_route = <<'DISCONNECT_ROUTE';
$router->post(
    '/admin/communications/settings/bale-connections/disconnect',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        /*
         * Reuse the established Bale invitation
         * capability until route permissions become
         * independently configurable.
         */
        $permissionPath =
            '/admin/communications/settings/send/bale-invitations';

        if (!$communicationAccess(
            $context,
            'POST',
            $permissionPath
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections'
                . '&status=invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\NotificationBaleConnectionManagementService()
            )->disconnect(
                (int) $context['user_id'],
                (int) $request->input('user_id', 0)
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections'
                . '&status=notification_bale_connection_disconnected'
            );
        } catch (\Throwable $exception) {
            $status = trim(
                $exception->getMessage()
            );

            if (!str_starts_with(
                $status,
                'notification_bale_'
            )) {
                $status =
                    'notification_bale_connection_disconnect_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections&status='
                . rawurlencode($status)
            );
        }
    }
);

DISCONNECT_ROUTE

replace_once(
    \$routes,
    $webhook_anchor,
    $disconnect_route . $webhook_anchor,
    'Bale connection disconnect route'
);

write_text($routes_path, $routes);
PERL

echo
echo "=== Add Bale Connection Management Styles ==="

if grep -Fq \
  "bale-connection-management-style-v061" \
  "$style_file"
then
    echo "Bale connection styles already exist." >&2
    exit 1
fi

cat >> "$style_file" <<'CSS'

<style>
/* bale-connection-management-style-v061 */
.bale-connection-management {
    display: grid;
    gap: 1rem;
}

.bale-connection-intro {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: 1rem;
}

.bale-connection-intro h3 {
    margin: 0;
}

.bale-connection-provider {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .2rem;
    min-width: 250px;
    padding: .75rem .9rem;
}

.bale-connection-provider > span,
.bale-connection-provider > small {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.bale-connection-provider__state {
    border-radius: 999px;
    font-size: .74rem;
    font-style: normal;
    margin-top: .25rem;
    padding: .25rem .5rem;
}

.bale-connection-provider__state--success {
    background: #e8f7ef;
    color: #17643c;
}

.bale-connection-provider__state--error {
    background: #fff1f1;
    color: #a33a3a;
}

.bale-connection-summary {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.bale-connection-summary article {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    gap: .3rem;
    padding: .75rem;
}

.bale-connection-summary span {
    color: var(--admin-text-muted);
    font-size: .76rem;
}

.bale-connection-summary strong {
    font-size: 1.15rem;
}

.bale-connection-form {
    display: grid;
    gap: .8rem;
}

.bale-connection-filters {
    align-items: end;
    display: grid;
    gap: .65rem;
    grid-template-columns:
        minmax(240px, 2fr)
        repeat(4, minmax(135px, 1fr));
}

.bale-connection-filters label {
    display: grid;
    gap: .3rem;
}

.bale-connection-filters label > span {
    color: var(--admin-text-muted);
    font-size: .76rem;
    font-weight: 700;
}

.bale-connection-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.bale-connection-actions [data-bale-send-invitations] {
    margin-inline-start: auto;
}

.bale-connection-user-list {
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    display: grid;
    max-height: 560px;
    overflow: auto;
}

.bale-connection-user {
    align-items: center;
    border-bottom: 1px solid var(--admin-border);
    display: grid;
    gap: .7rem;
    grid-template-columns:
        auto
        minmax(210px, 1.5fr)
        minmax(100px, .7fr)
        minmax(180px, 1fr)
        minmax(115px, auto);
    padding: .7rem .8rem;
}

.bale-connection-user:last-child {
    border-bottom: 0;
}

.bale-connection-user:hover {
    background: var(--admin-surface-muted);
}

.bale-connection-user__select {
    align-items: center;
    display: flex;
}

.bale-connection-user__identity,
.bale-connection-user__mobile,
.bale-connection-user__status {
    display: grid;
    gap: .2rem;
    min-width: 0;
}

.bale-connection-user__identity small,
.bale-connection-user__mobile > span,
.bale-connection-user__status > small,
.bale-connection-user__row-actions > small {
    color: var(--admin-text-muted);
    font-size: .75rem;
}

.bale-connection-user__mobile strong {
    font-size: .78rem;
}

.bale-connection-user__mobile strong.is-ready {
    color: #17643c;
}

.bale-connection-user__mobile strong.is-missing {
    color: #a33a3a;
}

.bale-connection-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: .76rem;
    font-weight: 700;
    justify-self: start;
    padding: .25rem .5rem;
}

.bale-connection-status--connected {
    background: #e8f7ef;
    color: #17643c;
}

.bale-connection-status--invited,
.bale-connection-status--waiting_confirmation {
    background: #eef4ff;
    color: #315c9a;
}

.bale-connection-status--expired,
.bale-connection-status--failed {
    background: #fff1f1;
    color: #a33a3a;
}

.bale-connection-status--disconnected,
.bale-connection-status--not_connected {
    background: var(--admin-surface-muted);
    color: var(--admin-text-muted);
}

.bale-connection-user__row-actions {
    align-items: center;
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 1280px) {
    .bale-connection-summary {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .bale-connection-filters {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .bale-connection-filter-search {
        grid-column: span 2;
    }

    .bale-connection-user {
        grid-template-columns:
            auto
            minmax(200px, 1fr)
            minmax(160px, .8fr)
            minmax(110px, auto);
    }

    .bale-connection-user__mobile {
        display: none;
    }
}

@media (max-width: 760px) {
    .bale-connection-intro {
        align-items: stretch;
        flex-direction: column;
    }

    .bale-connection-provider {
        min-width: 0;
    }

    .bale-connection-summary,
    .bale-connection-filters {
        grid-template-columns: 1fr;
    }

    .bale-connection-filter-search {
        grid-column: auto;
    }

    .bale-connection-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .bale-connection-actions > * {
        margin-inline-start: 0 !important;
        width: 100%;
    }

    .bale-connection-user {
        align-items: flex-start;
        grid-template-columns: auto minmax(0, 1fr);
    }

    .bale-connection-user__status,
    .bale-connection-user__row-actions {
        grid-column: 2;
    }

    .bale-connection-user__row-actions {
        justify-content: flex-start;
    }
}
</style>
CSS

echo "UPDATED: communication styles"

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

$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationMessengerEnrollmentRepository.php'
);
$enrollmentService = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);
$managementService = $read(
    'public_html/app/Services/'
    . 'NotificationBaleConnectionManagementService.php'
);
$settingsService = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    str_contains(
        $settingsService,
        "'bale_connections'"
    )
    && str_contains(
        $settingsService,
        'NotificationBaleConnectionManagementService'
    ),
    'Bale connection settings section is incomplete.'
);

$expect(
    str_contains(
        $repository,
        'connectionStatuses'
    )
    && str_contains(
        $repository,
        'revokeBinding'
    )
    && str_contains(
        $repository,
        'has_active_binding'
    ),
    'Bale connection repository support is incomplete.'
);

$expect(
    str_contains(
        $enrollmentService,
        'recipient_already_connected'
    )
    && str_contains(
        $managementService,
        'can_invite_bale'
    )
    && str_contains(
        $managementService,
        'can_disconnect_bale'
    ),
    'Bale connection management logic is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/bale-connections/disconnect'
    )
    && str_contains(
        $routes,
        '?section=bale_connections'
    ),
    'Bale connection routes are incomplete.'
);

$expect(
    str_contains(
        $view,
        'bale-connection-management-v061'
    )
    && str_contains(
        $view,
        'ارسال دعوت اتصال با پیامک'
    )
    && str_contains(
        $view,
        'data-bale-disconnect-user'
    )
    && !str_contains(
        $view,
        'userActions?.append(inviteButton)'
    ),
    'Dedicated Bale connection UI is incomplete.'
);

$expect(
    str_contains(
        $style,
        'bale-connection-management-style-v061'
    )
    && str_contains(
        $style,
        '.bale-connection-status--connected'
    ),
    'Bale connection styles are incomplete.'
);

echo "Bale connection management UI checks passed.\n";
PHP

echo "ADDED: BaleConnectionManagementUiTest.php"

mkdir -p tools
cp -- "$0" "$tool_file"

git add -- \
  "$repository_file" \
  "$enrollment_service_file" \
  "$management_service_file" \
  "$settings_service_file" \
  "$routes_file" \
  "$view_file" \
  "$style_file" \
  "$test_file" \
  "$tool_file"

echo
echo "=== Cached Validation ==="

git diff --cached --check

echo
echo "=== Bale Connection Management Markers ==="

git grep -n -E \
  "bale_connections|bale-connection-management-v061|connectionStatuses|revokeBinding|recipient_already_connected|bale-connections/disconnect|bale-connection-management-style-v061" \
  -- \
  "$repository_file" \
  "$enrollment_service_file" \
  "$management_service_file" \
  "$settings_service_file" \
  "$routes_file" \
  "$view_file" \
  "$style_file" \
  "$test_file"

echo
echo "=== Legacy Button Check ==="

if git grep -n \
  "userActions?.append(inviteButton)" \
  -- "$view_file"
then
    echo "Legacy invitation button injection still exists." >&2
    exit 1
else
    echo "LEGACY_INVITATION_BUTTON=REMOVED"
fi

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
echo "BALE CONNECTION MANAGEMENT UI ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
