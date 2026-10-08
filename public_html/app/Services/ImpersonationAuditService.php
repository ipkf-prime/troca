<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImpersonationAuditRepository;
use InvalidArgumentException;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Logging\AuditLogger;
use IPKF\Logging\DatabaseAuditLogger;
use PDO;
use Throwable;

final class ImpersonationAuditService
{
    private const PLATFORM_EVENT_MAP = [
        'impersonation_started' =>
            'Auth.Impersonation.Started',

        'impersonation_ended' =>
            'Auth.Impersonation.Ended',

        'impersonation_expired' =>
            'Auth.Impersonation.Expired',

        'impersonation_start_denied' =>
            'Auth.Impersonation.StartDenied',

        'impersonation_restore_denied' =>
            'Auth.Impersonation.RestoreDenied',

        'impersonation_mutation_blocked' =>
            'Auth.Impersonation.MutationBlocked',

        'impersonation_operation_attempted' =>
            'Auth.Impersonation.OperationAttempted',

        'impersonation_operation_completed' =>
            'Auth.Impersonation.OperationCompleted',
    ];

    private PDO $db;

    private ImpersonationAuditRepository $dedicated;

    private AuditLogger $platform;


    public function __construct(
        ?PDO $db = null,
        ?ImpersonationAuditRepository $dedicated = null,
        ?AuditLogger $platform = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );

        $this->dedicated =
            $dedicated
            ?? new ImpersonationAuditRepository(
                $this->db
            );

        $this->platform =
            $platform
            ?? new DatabaseAuditLogger(
                $this->db
            );
    }


    public function record(
        string $eventCode,
        int $actorUserId,
        int $effectiveUserId,
        ?string $reasonCode = null,
        array $metadata = []
    ): string {
        $platformEvent =
            self::PLATFORM_EVENT_MAP[
                $eventCode
            ]
            ?? null;

        if ($platformEvent === null) {
            throw new InvalidArgumentException(
                'invalid_impersonation_event_code'
            );
        }

        $ownsTransaction =
            !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $reference =
                $this->dedicated->record(
                    $eventCode,
                    $actorUserId,
                    $effectiveUserId,
                    $reasonCode,
                    $metadata
                );

            $this->platform->record(
                $platformEvent,
                [
                    'module' =>
                        'core',

                    'action' =>
                        'impersonate',

                    'actor_user_id' =>
                        $actorUserId,

                    'actor_type' =>
                        'user',

                    'target_type' =>
                        'user',

                    'target_id' =>
                        (string) $effectiveUserId,

                    'result' =>
                        $this->resultCode(
                            $eventCode,
                            $metadata
                        ),

                    'reason' =>
                        $reasonCode,

                    'metadata' =>
                        [
                            ...$metadata,

                            'effective_user_id' =>
                                $effectiveUserId,

                            'impersonation_event_reference' =>
                                $reference,
                        ],
                ]
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $reference;

        } catch (Throwable $exception) {

            if (
                $ownsTransaction
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    private function resultCode(
        string $eventCode,
        array $metadata = []
    ): string {
        return match ($eventCode) {
            'impersonation_start_denied',
            'impersonation_restore_denied' =>
                'denied',

            'impersonation_mutation_blocked' =>
                'blocked',

            'impersonation_operation_attempted' =>
                'attempted',

            'impersonation_operation_completed' =>
                (
                    (int) (
                        $metadata[
                            'http_status'
                        ]
                        ?? 500
                    ) >= 200
                    &&
                    (int) (
                        $metadata[
                            'http_status'
                        ]
                        ?? 500
                    ) < 400
                )
                    ? 'success'
                    : 'failed',

            'impersonation_expired' =>
                'expired',

            default =>
                'success',
        };
    }
}
