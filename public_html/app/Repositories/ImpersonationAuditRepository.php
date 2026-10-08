<?php

declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Logging\RequestContext;
use IPKF\Logging\SecretMasker;
use PDO;
use RuntimeException;
use Throwable;

final class ImpersonationAuditRepository
{
    private const EVENT_CODES = [
        'impersonation_started',
        'impersonation_ended',
        'impersonation_expired',
        'impersonation_start_denied',
        'impersonation_restore_denied',
        'impersonation_mutation_blocked',
        'impersonation_operation_attempted',
        'impersonation_operation_completed',
    ];

    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );
    }


    public function record(
        string $eventCode,
        int $actorUserId,
        int $effectiveUserId,
        ?string $reasonCode = null,
        array $metadata = []
    ): string {
        $eventCode =
            trim(
                $eventCode
            );

        if (
            !in_array(
                $eventCode,
                self::EVENT_CODES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_event_code'
            );
        }

        if (
            $actorUserId < 1
            || $effectiveUserId < 1
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_audit_user'
            );
        }

        if (
            $reasonCode !== null
            && preg_match(
                '/^[a-z0-9][a-z0-9._-]{0,79}$/',
                $reasonCode
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'invalid_impersonation_reason_code'
            );
        }

        RequestContext::ensure();

        $reference =
            $this->publicReference();

        $metadataJson =
            json_encode(
                SecretMasker::sanitize(
                    $metadata
                ),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            );

        $statement =
            $this->db->prepare("
                INSERT INTO auth_impersonation_events
                (
                    public_reference,
                    actor_user_id,
                    effective_user_id,
                    event_code,
                    reason_code,
                    request_id,
                    correlation_id,
                    ip_address,
                    user_agent,
                    metadata_json,
                    occurred_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

        try {
            $statement->execute([
                $reference,
                $actorUserId,
                $effectiveUserId,
                $eventCode,
                $reasonCode,
                RequestContext::requestId(),
                RequestContext::correlationId(),
                RequestContext::ip(),
                RequestContext::userAgent(),
                $metadataJson,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'unable_to_persist_impersonation_audit_event',
                0,
                $exception
            );
        }

        return $reference;
    }


    private function publicReference(): string
    {
        try {
            $suffix =
                strtoupper(
                    bin2hex(
                        random_bytes(16)
                    )
                );
        } catch (Throwable) {
            $suffix =
                strtoupper(
                    substr(
                        hash(
                            'sha256',
                            uniqid(
                                '',
                                true
                            )
                            . microtime(true)
                        ),
                        0,
                        32
                    )
                );
        }

        return
            'IMP-'
            . $suffix;
    }
}
