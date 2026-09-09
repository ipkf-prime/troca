<?php

declare(strict_types=1);

namespace IPKF\Logging;

use InvalidArgumentException;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseAuditLogger implements AuditLogger
{
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
        array $record
    ): void {
        $eventCode =
            trim(
                $eventCode
            );


        if (
            preg_match(
                '/^[A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*){1,7}$/',
                $eventCode
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid platform audit event code.'
            );
        }


        $module =
            $this->asciiIdentifier(
                $record[
                    'module'
                ]
                ?? null,
                80
            );


        if ($module === null) {
            throw new InvalidArgumentException(
                'Platform audit module is required.'
            );
        }


        RequestContext::ensure();


        $before =
            $this->sanitizedPayload(
                $record[
                    'before'
                ]
                ?? null
            );

        $after =
            $this->sanitizedPayload(
                $record[
                    'after'
                ]
                ?? null
            );


        $changedFields =
            $record[
                'changed_fields'
            ]
            ?? $this->deriveChangedFields(
                $before,
                $after
            );


        $metadata =
            $this->sanitizedPayload(
                $record[
                    'metadata'
                ]
                ?? []
            );


        $statement =
            $this->db->prepare("
                INSERT INTO platform_audit_events (
                    public_reference,
                    schema_version,
                    event_code,
                    module_code,
                    action_code,

                    actor_user_id,
                    actor_user_reference,
                    actor_type,
                    actor_display_name_snapshot,

                    target_type,
                    target_id,
                    target_reference,

                    result_code,
                    reason,

                    before_json,
                    after_json,
                    changed_fields_json,
                    metadata_json,

                    request_id,
                    correlation_id,
                    ip_address,
                    user_agent,

                    retention_policy_code,
                    retain_until,

                    occurred_at
                )
                VALUES (
                    ?,
                    1,
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

                    ?,
                    ?,

                    UTC_TIMESTAMP()
                )
            ");


        try {
            $statement->execute(
                [
                    $this->publicReference(),

                    $eventCode,
                    $module,

                    $this->asciiIdentifier(
                        $record[
                            'action'
                        ]
                        ?? null,
                        100
                    ),

                    $this->actorUserId(
                        $record[
                            'actor_user_id'
                        ]
                        ?? RequestContext::actorUserId()
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'actor_user_reference'
                        ]
                        ?? RequestContext::actorUserReference(),
                        100
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'actor_type'
                        ]
                        ?? RequestContext::actorType(),
                        40
                    ),

                    $this->text(
                        $record[
                            'actor_display_name'
                        ]
                        ?? null,
                        255
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'target_type'
                        ]
                        ?? null,
                        80
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'target_id'
                        ]
                        ?? null,
                        190
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'target_reference'
                        ]
                        ?? null,
                        190
                    ),

                    $this->asciiIdentifier(
                        $record[
                            'result'
                        ]
                        ?? null,
                        60
                    ),

                    $this->text(
                        $record[
                            'reason'
                        ]
                        ?? null,
                        1000
                    ),

                    $this->json(
                        $before
                    ),

                    $this->json(
                        $after
                    ),

                    $this->json(
                        SecretMasker::sanitize(
                            $changedFields
                        )
                    ),

                    $this->json(
                        $metadata
                    ),

                    RequestContext::requestId(),
                    RequestContext::correlationId(),
                    RequestContext::ip(),
                    RequestContext::userAgent(),

                    $this->asciiIdentifier(
                        $record[
                            'retention_policy_code'
                        ]
                        ?? null,
                        80
                    ),

                    $this->dateTime(
                        $record[
                            'retain_until'
                        ]
                        ?? null
                    ),
                ]
            );

        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unable to persist platform audit event.',
                0,
                $exception
            );
        }
    }


    private function actorUserId(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }


        $value =
            (int) $value;


        return $value > 0
            ? $value
            : null;
    }


    private function sanitizedPayload(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        return SecretMasker::sanitize(
            $value
        );
    }


    private function deriveChangedFields(
        mixed $before,
        mixed $after
    ): array {
        if (
            !is_array(
                $before
            )
            ||
            !is_array(
                $after
            )
        ) {
            return [];
        }


        $keys =
            array_values(
                array_unique(
                    array_merge(
                        array_keys(
                            $before
                        ),
                        array_keys(
                            $after
                        )
                    )
                )
            );


        $changed = [];


        foreach (
            $keys
            as $key
        ) {
            $beforeValue =
                $before[$key]
                ?? null;

            $afterValue =
                $after[$key]
                ?? null;


            if (
                $beforeValue
                !== $afterValue
            ) {
                $changed[] =
                    (string) $key;
            }
        }


        sort(
            $changed,
            SORT_STRING
        );


        return $changed;
    }


    private function asciiIdentifier(
        mixed $value,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }


        $value =
            trim(
                (string) $value
            );


        if ($value === '') {
            return null;
        }


        if (
            preg_match(
                '/^[\x20-\x7E]+$/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Audit technical identifier must be ASCII.'
            );
        }


        if (
            strlen(
                $value
            ) > $maxLength
        ) {
            throw new InvalidArgumentException(
                'Audit technical identifier is too long.'
            );
        }


        return $value;
    }


    private function text(
        mixed $value,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }


        $value =
            SecretMasker::sanitizeText(
                trim(
                    (string) $value
                )
            );


        if ($value === '') {
            return null;
        }


        if (
            function_exists(
                'mb_substr'
            )
        ) {
            return mb_substr(
                $value,
                0,
                $maxLength,
                'UTF-8'
            );
        }


        return substr(
            $value,
            0,
            $maxLength
        );
    }


    private function json(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }


        $json =
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );


        if (!is_string($json)) {
            throw new RuntimeException(
                'Unable to encode platform audit JSON.'
            );
        }


        return $json;
    }


    private function dateTime(
        mixed $value
    ): ?string {
        if (
            $value === null
            || trim(
                (string) $value
            ) === ''
        ) {
            return null;
        }


        $value =
            trim(
                (string) $value
            );


        $date =
            \DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value,
                new \DateTimeZone(
                    'UTC'
                )
            );


        $errors =
            \DateTimeImmutable::getLastErrors();


        if (
            $date === false
            ||
            (
                is_array(
                    $errors
                )
                &&
                (
                    (int) (
                        $errors[
                            'warning_count'
                        ]
                        ?? 0
                    ) > 0
                    ||
                    (int) (
                        $errors[
                            'error_count'
                        ]
                        ?? 0
                    ) > 0
                )
            )
        ) {
            throw new InvalidArgumentException(
                'retain_until must be UTC Y-m-d H:i:s.'
            );
        }


        return $date->format(
            'Y-m-d H:i:s'
        );
    }


    private function publicReference(): string
    {
        try {
            return 'AUD-'
                . strtoupper(
                    bin2hex(
                        random_bytes(
                            16
                        )
                    )
                );

        } catch (Throwable) {
            return 'AUD-'
                . strtoupper(
                    substr(
                        hash(
                            'sha256',
                            uniqid(
                                '',
                                true
                            )
                            . microtime(
                                true
                            )
                        ),
                        0,
                        32
                    )
                );
        }
    }
}
