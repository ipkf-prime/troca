<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Clock;
use PDO;
use RuntimeException;
use Throwable;

final class CorrespondenceDispatchService
{
    private const ALLOWED_CHANNELS = [
        'postal',
        'courier',
        'hand_delivery',
        'fax',
        'email',
        'system',
    ];

    private const ACTIVE_REQUEST_STATUSES = [
        'prepared',
        'pending',
        'queued',
        'dispatched',
        'delivered',
    ];

    private AutomationOperationalRuntime $runtime;

    private CorrespondenceRepository $correspondences;

    private EnterpriseAutomationContextService $enterpriseContext;

    private CorrespondenceDispatchTargetResolver $targets;

    public function __construct(
        ?AutomationOperationalRuntime $runtime = null,
        ?CorrespondenceRepository $correspondences = null,
        ?EnterpriseAutomationContextService $enterpriseContext = null,
        ?CorrespondenceDispatchTargetResolver $targets = null
    ) {
        $this->runtime =
            $runtime
            ?? new AutomationOperationalRuntime();

        $this->correspondences =
            $correspondences
            ?? new CorrespondenceRepository(
                $this->runtime
            );

        $this->enterpriseContext =
            $enterpriseContext
            ?? new EnterpriseAutomationContextService();

        $this->targets =
            $targets
            ?? new CorrespondenceDispatchTargetResolver();
    }

    /*
     * dispatch-request-lifecycle-d2
     *
     * This method records dispatch intent only.
     *
     * It MUST NOT:
     * - call a provider,
     * - create an attempt,
     * - mark the correspondence dispatched,
     * - change correspondences.channel_code,
     * - set correspondences.dispatched_at.
     */
    public function request(
        string $publicReference,
        int $userId,
        string $channelCode
    ): array {
        $publicReference =
            trim(
                $publicReference
            );

        $channelCode =
            strtolower(
                trim(
                    $channelCode
                )
            );

        if (
            $publicReference === ''
            || $userId < 1
        ) {
            return $this->failure(
                'invalid_dispatch_request'
            );
        }

        if (
            !in_array(
                $channelCode,
                self::ALLOWED_CHANNELS,
                true
            )
        ) {
            return $this->failure(
                'invalid_dispatch_channel'
            );
        }

        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

        } catch (Throwable) {
            return $this->failure(
                'organizational_context_required'
            );
        }

        $pdo =
            $this->runtime
                ->connection();

        $now =
            Clock::databaseTimestamp();

        try {
            $pdo->beginTransaction();

            $correspondence =
                $this->correspondences
                    ->findByPublicReferenceForUpdate(
                        $publicReference
                    );

            if ($correspondence === null) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_found'
                );
            }

            try {
                $this->enterpriseContext
                    ->assertCorrespondenceAccess(
                        $correspondence,
                        $actor
                    );

            } catch (RuntimeException) {
                $pdo->rollBack();

                return $this->failure(
                    'forbidden_scope'
                );
            }

            if (
                (string) (
                    $correspondence[
                        'direction_code'
                    ]
                    ?? ''
                ) !== 'outgoing'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_requires_outgoing'
                );
            }

            if (
                (string) (
                    $correspondence[
                        'status_code'
                    ]
                    ?? ''
                ) !== 'registered'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_dispatchable'
                );
            }

            if (
                $this->officialRegistration(
                    $pdo,
                    (int) $correspondence['id']
                ) === null
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'official_registration_required'
                );
            }

            $sender =
                $this->sender(
                    $pdo,
                    (int) $correspondence['id']
                );

            if ($sender === null) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_source_unavailable'
                );
            }

            $recipients =
                $this->primaryRecipients(
                    $pdo,
                    (int) $correspondence['id']
                );

            if ($recipients === []) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_target_required'
                );
            }

            /*
             * Resolve ALL destinations before inserting
             * anything. One invalid recipient fails the
             * whole request atomically.
             */
            $resolvedTargets = [];

            foreach ($recipients as $recipient) {
                $resolved =
                    $this->targets->resolve(
                        $recipient,
                        $channelCode
                    );

                if (
                    ($resolved['ok'] ?? false)
                    !== true
                ) {
                    $pdo->rollBack();

                    return $this->failure(
                        (string) (
                            $resolved['error']
                            ?? 'dispatch_destination_unavailable'
                        )
                    );
                }

                $resolvedTargets[] = [
                    'party' =>
                        $recipient,

                    'resolved' =>
                        $resolved,
                ];
            }

            $sourceSnapshot =
                $this->json([
                    'correspondence_party_id' =>
                        (int) $sender['id'],

                    'party_role_code' =>
                        (string) (
                            $sender[
                                'party_role_code'
                            ]
                            ?? ''
                        ),

                    'target_kind_code' =>
                        (string) (
                            $sender[
                                'target_kind_code'
                            ]
                            ?? ''
                        ),

                    'person_id' =>
                        $sender['person_id']
                        ?? null,

                    'organization_id' =>
                        $sender[
                            'organization_id'
                        ]
                        ?? null,

                    'org_unit_id' =>
                        $sender[
                            'org_unit_id'
                        ]
                        ?? null,
                ]);

            $actorContextSnapshot =
                $this->json(
                    $actor
                );

            $appointmentReference =
                trim(
                    (string) (
                        $actor[
                            'appointment_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                    $appointmentReference
                ) !== 1
            ) {
                $appointmentReference =
                    null;
            }

            $created = [];
            $existing = [];

            foreach (
                $resolvedTargets
                as $item
            ) {
                $recipient =
                    $item['party'];

                $resolved =
                    $item['resolved'];

                $active =
                    $this->activeRequest(
                        $pdo,
                        (int) $correspondence['id'],
                        (int) $recipient['id'],
                        $channelCode
                    );

                if (is_array($active)) {
                    $existing[] = [
                        'public_reference' =>
                            (string) $active[
                                'public_reference'
                            ],

                        'status_code' =>
                            (string) $active[
                                'status_code'
                            ],
                    ];

                    continue;
                }

                $dispatchReference =
                    $this->reference();

                $insert =
                    $pdo->prepare("
                        INSERT INTO correspondence_dispatches (
                            public_reference,
                            correspondence_id,
                            correspondence_party_id,

                            root_organization_id,
                            organization_id,
                            secretariat_desk_id,

                            target_kind_code,

                            external_organization_id,
                            external_organization_public_reference,

                            external_contact_point_id,
                            external_contact_point_public_reference,

                            channel_code,

                            target_snapshot_json,
                            source_snapshot_json,
                            destination_snapshot_json,

                            status_code,

                            tracking_code,
                            provider_reference,

                            requested_by_user_id,
                            requested_appointment_reference,
                            actor_context_snapshot_json,

                            requested_at,

                            dispatched_at,
                            delivered_at,
                            failed_at,
                            cancelled_at,

                            failure_code,
                            failure_message,

                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,

                            ?,
                            ?,
                            ?,

                            'external',

                            ?,
                            ?,

                            ?,
                            ?,

                            ?,

                            ?,
                            ?,
                            ?,

                            'pending',

                            NULL,
                            NULL,

                            ?,
                            ?,
                            ?,

                            ?,

                            NULL,
                            NULL,
                            NULL,
                            NULL,

                            NULL,
                            NULL,

                            ?,
                            ?
                        )
                    ");

                $insert->execute([
                    $dispatchReference,

                    (int) $correspondence['id'],
                    (int) $recipient['id'],

                    $this->nullableInt(
                        $correspondence[
                            'root_organization_id'
                        ]
                        ?? null
                    ),

                    $this->nullableInt(
                        $correspondence[
                            'organization_id'
                        ]
                        ?? null
                    ),

                    $this->nullableInt(
                        $correspondence[
                            'secretariat_desk_id'
                        ]
                        ?? null
                    ),

                    (int) $resolved[
                        'external_organization_id'
                    ],

                    (string) $resolved[
                        'external_organization_public_reference'
                    ],

                    (int) $resolved[
                        'external_contact_point_id'
                    ],

                    (string) $resolved[
                        'external_contact_point_public_reference'
                    ],

                    $channelCode,

                    $this->json(
                        $resolved[
                            'target_snapshot'
                        ]
                    ),

                    $sourceSnapshot,

                    $this->json(
                        $resolved[
                            'destination_snapshot'
                        ]
                    ),

                    $userId,
                    $appointmentReference,
                    $actorContextSnapshot,

                    $now,

                    $now,
                    $now,
                ]);

                if (
                    $insert->rowCount()
                    !== 1
                ) {
                    throw new RuntimeException(
                        'dispatch_request_insert_failed'
                    );
                }

                $created[] = [
                    'public_reference' =>
                        $dispatchReference,

                    'status_code' =>
                        'pending',
                ];
            }

            $pdo->commit();

            return [
                'ok' => true,

                'requested' =>
                    $created,

                'existing' =>
                    $existing,

                'created_count' =>
                    count($created),

                'existing_count' =>
                    count($existing),

                'channel_code' =>
                    $channelCode,

                'correspondence_status_changed' =>
                    false,

                'provider_attempted' =>
                    false,
            ];

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $known = [
                'dispatch_request_insert_failed',
            ];

            return $this->failure(
                in_array(
                    $exception->getMessage(),
                    $known,
                    true
                )
                    ? $exception->getMessage()
                    : 'runtime_unavailable'
            );
        }
    }

    private function sender(
        PDO $pdo,
        int $correspondenceId
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT *
                FROM correspondence_parties
                WHERE
                    correspondence_id = ?
                    AND party_role_code = 'sender'
                ORDER BY
                    sort_order,
                    id
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        return
            count($rows) === 1
                ? $rows[0]
                : null;
    }

    private function primaryRecipients(
        PDO $pdo,
        int $correspondenceId
    ): array {
        $statement =
            $pdo->prepare("
                SELECT *
                FROM correspondence_parties
                WHERE
                    correspondence_id = ?
                    AND party_role_code =
                        'primary_recipient'
                ORDER BY
                    sort_order,
                    id
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    private function activeRequest(
        PDO $pdo,
        int $correspondenceId,
        int $partyId,
        string $channelCode
    ): ?array {
        $placeholders =
            implode(
                ', ',
                array_fill(
                    0,
                    count(
                        self::ACTIVE_REQUEST_STATUSES
                    ),
                    '?'
                )
            );

        $statement =
            $pdo->prepare("
                SELECT
                    id,
                    public_reference,
                    status_code

                FROM correspondence_dispatches

                WHERE
                    correspondence_id = ?
                    AND correspondence_party_id = ?
                    AND channel_code = ?
                    AND status_code IN (
                        {$placeholders}
                    )

                ORDER BY id DESC

                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $correspondenceId,
            $partyId,
            $channelCode,
            ...self::ACTIVE_REQUEST_STATUSES,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function officialRegistration(
        PDO $pdo,
        int $correspondenceId
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT
                    id,
                    formatted_number,
                    registered_at

                FROM correspondence_registrations

                WHERE correspondence_id = ?
                  AND registration_role_code =
                        'official'
                  AND status_code =
                        'active'
                  AND cancelled_at IS NULL

                ORDER BY id DESC
                LIMIT 1
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function reference(): string
    {
        return
            'DSP-'
            . gmdate('Ymd')
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(5)
                )
            );
    }

    private function json(
        array $value
    ): string {
        return
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
    }

    private function nullableInt(
        mixed $value
    ): ?int {
        $value =
            (int) (
                $value
                ?? 0
            );

        return
            $value > 0
                ? $value
                : null;
    }

    private function failure(
        string $code
    ): array {
        return [
            'ok' => false,
            'errors' => [
                $code,
            ],
        ];
    }
}
