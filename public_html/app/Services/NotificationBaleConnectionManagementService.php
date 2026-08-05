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
