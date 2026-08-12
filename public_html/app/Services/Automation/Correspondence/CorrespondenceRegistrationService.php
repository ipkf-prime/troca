<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Clock;
use PDO;
use RuntimeException;
use Throwable;

final class CorrespondenceRegistrationService
{
    private AutomationOperationalRuntime $runtime;

    private CorrespondenceRepository $correspondences;

    private CorrespondenceEventRepository $events;

    private EnterpriseAutomationContextService $enterpriseContext;

    public function __construct(
        ?AutomationOperationalRuntime $runtime = null,
        ?CorrespondenceRepository $correspondences = null,
        ?CorrespondenceEventRepository $events = null,
        ?EnterpriseAutomationContextService $enterpriseContext = null
    ) {
        $this->runtime =
            $runtime
            ?? new AutomationOperationalRuntime();

        $this->correspondences =
            $correspondences
            ?? new CorrespondenceRepository(
                $this->runtime
            );

        $this->events =
            $events
            ?? new CorrespondenceEventRepository(
                $this->runtime
            );

        $this->enterpriseContext =
            $enterpriseContext
            ?? new EnterpriseAutomationContextService();
    }

    public function registerOfficial(
        string $publicReference,
        int $userId
    ): array {
        $publicReference =
            trim(
                $publicReference
            );

        if (
            $publicReference === ''
            || $userId < 1
        ) {
            return $this->failure(
                'invalid_registration_request'
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

            $existing =
                $this->activeOfficialRegistration(
                    $pdo,
                    (int) $correspondence['id']
                );

            if ($existing !== null) {
                $pdo->rollBack();

                return [
                    'ok' => true,
                    'already_registered' => true,
                    'formatted_number' =>
                        (string) (
                            $existing[
                                'formatted_number'
                            ] ?? ''
                        ),
                ];
            }

            if (
                (string) (
                    $correspondence[
                        'status_code'
                    ] ?? ''
                ) !== 'draft'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_registerable'
                );
            }

            $direction =
                (string) (
                    $correspondence[
                        'direction_code'
                    ] ?? ''
                );

            if (
                !in_array(
                    $direction,
                    [
                        'incoming',
                        'outgoing',
                        'internal',
                    ],
                    true
                )
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'invalid_correspondence_direction'
                );
            }

            $books =
                $this->eligibleBooks(
                    $pdo,
                    $correspondence,
                    $actor,
                    $direction
                );

            if ($books === []) {
                $pdo->rollBack();

                return $this->failure(
                    'registry_book_unavailable'
                );
            }

            if (count($books) !== 1) {
                $pdo->rollBack();

                return $this->failure(
                    'registry_book_ambiguous'
                );
            }

            $book = $books[0];

            $sequence =
                $this->lockSequence(
                    $pdo,
                    (int) $book[
                        'number_sequence_id'
                    ]
                );

            if ($sequence === null) {
                throw new RuntimeException(
                    'registry_sequence_missing'
                );
            }

            if (
                (string) (
                    $sequence['status']
                    ?? ''
                ) !== 'active'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'registry_sequence_inactive'
                );
            }

            if (
                (int) (
                    $sequence[
                        'secretariat_desk_id'
                    ] ?? 0
                )
                !==
                (int) $book[
                    'secretariat_desk_id'
                ]
                ||
                (int) (
                    $sequence[
                        'registry_period_id'
                    ] ?? 0
                )
                !==
                (int) $book[
                    'registry_period_id'
                ]
            ) {
                throw new RuntimeException(
                    'registry_sequence_scope_mismatch'
                );
            }

            $number =
                (int) (
                    $sequence[
                        'next_sequence_number'
                    ] ?? 0
                );

            if ($number < 1) {
                throw new RuntimeException(
                    'registry_sequence_invalid'
                );
            }

            $formattedNumber =
                $this->formatNumber(
                    $sequence,
                    $number
                );

            if ($formattedNumber === '') {
                throw new RuntimeException(
                    'registry_formatted_number_invalid'
                );
            }

            $nextNumber =
                $number + 1;

            $advance =
                $pdo->prepare("
                    UPDATE
                        registry_number_sequences

                    SET
                        next_sequence_number = ?,
                        updated_at = ?

                    WHERE id = ?
                      AND next_sequence_number = ?
                      AND status = 'active'
                ");

            $advance->execute([
                $nextNumber,
                $now,
                (int) $sequence['id'],
                $number,
            ]);

            if ($advance->rowCount() !== 1) {
                throw new RuntimeException(
                    'registry_sequence_conflict'
                );
            }

            /*
             * Keep the legacy book projection aligned
             * with the enterprise sequence authority.
             */
            $bookProjection =
                $pdo->prepare("
                    UPDATE registry_books

                    SET next_sequence_number = ?,
                        updated_at = ?

                    WHERE number_sequence_id = ?
                ");

            $bookProjection->execute([
                $nextNumber,
                $now,
                (int) $sequence['id'],
            ]);

            $reservationId =
                $this->consumeReservation(
                    $pdo,
                    $correspondence,
                    $actor,
                    $book,
                    $sequence,
                    $number,
                    $formattedNumber,
                    $now
                );

            $registration =
                $pdo->prepare("
                    INSERT INTO
                        correspondence_registrations (
                            root_organization_id,
                            organization_id,
                            secretariat_desk_id,
                            registry_period_id,
                            number_sequence_id,
                            number_reservation_id,

                            correspondence_id,
                            registry_book_id,

                            registration_role_code,
                            sequential_number,
                            formatted_number,
                            registered_at,

                            registered_by_user_id,
                            registered_appointment_reference,
                            actor_context_snapshot_json,

                            status_code,

                            created_at,
                            updated_at
                        )

                    VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?,
                        'official', ?, ?, ?,
                        ?, ?, ?,
                        'active',
                        ?, ?
                    )
                ");

            $registration->execute([
                (int) $actor[
                    'root_organization_id'
                ],

                (int) $correspondence[
                    'organization_id'
                ],

                (int) $book[
                    'secretariat_desk_id'
                ],

                (int) $book[
                    'registry_period_id'
                ],

                (int) $sequence['id'],

                $reservationId,

                (int) $correspondence['id'],

                (int) $book[
                    'registry_book_id'
                ],

                $number,
                $formattedNumber,
                $now,

                $userId,

                (string) $actor[
                    'appointment_reference'
                ],

                (string) $actor[
                    'snapshot_json'
                ],

                $now,
                $now,
            ]);

            $updateCorrespondence =
                $pdo->prepare("
                    UPDATE correspondences

                    SET
                        secretariat_desk_id = ?,
                        status_code = 'registered',
                        registered_at = ?,
                        updated_by_user_id = ?,
                        updated_at = ?,
                        lock_version =
                            lock_version + 1

                    WHERE id = ?
                      AND status_code = 'draft'
                ");

            $updateCorrespondence->execute([
                (int) $book[
                    'secretariat_desk_id'
                ],
                $now,
                $userId,
                $now,
                (int) $correspondence['id'],
            ]);

            if (
                $updateCorrespondence
                    ->rowCount() !== 1
            ) {
                throw new RuntimeException(
                    'correspondence_registration_conflict'
                );
            }

            $eventActor = $actor;

            $eventActor[
                'secretariat_desk_id'
            ] =
                (int) $book[
                    'secretariat_desk_id'
                ];

            $this->events->append(
                (int) $correspondence['id'],
                'registered',
                $userId,
                'draft',
                'registered',
                [
                    'registration_role' =>
                        'official',

                    'registration_number' =>
                        $formattedNumber,

                    'sequential_number' =>
                        $number,

                    'registry_book_reference' =>
                        (string) (
                            $book[
                                'registry_book_reference'
                            ] ?? ''
                        ),

                    'number_sequence_reference' =>
                        (string) (
                            $sequence[
                                'public_reference'
                            ] ?? ''
                        ),
                ],
                $now,
                $eventActor
            );

            $pdo->commit();

            return [
                'ok' => true,
                'already_registered' => false,

                'formatted_number' =>
                    $formattedNumber,

                'sequential_number' =>
                    $number,

                'registered_at' =>
                    $now,
            ];

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $known = [
                'registry_sequence_missing',
                'registry_sequence_scope_mismatch',
                'registry_sequence_invalid',
                'registry_formatted_number_invalid',
                'registry_sequence_conflict',
                'correspondence_registration_conflict',
            ];

            $code =
                in_array(
                    $exception->getMessage(),
                    $known,
                    true
                )
                    ? $exception->getMessage()
                    : 'runtime_unavailable';

            return $this->failure(
                $code
            );
        }
    }

    private function activeOfficialRegistration(
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
                  AND status_code = 'active'
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

    private function eligibleBooks(
        PDO $pdo,
        array $correspondence,
        array $actor,
        string $direction
    ): array {
        $registerColumn =
            match ($direction) {
                'incoming' =>
                    'can_register_incoming',

                'outgoing' =>
                    'can_register_outgoing',

                'internal' =>
                    'can_register_internal',
            };

        $supportColumn =
            match ($direction) {
                'incoming' =>
                    'supports_incoming',

                'outgoing' =>
                    'supports_outgoing',

                'internal' =>
                    'supports_internal',
            };

        $sql = "
            SELECT
                b.id AS registry_book_id,

                b.public_reference
                    AS registry_book_reference,

                b.secretariat_desk_id,
                b.registry_period_id,
                b.number_sequence_id,

                s.public_reference
                    AS sequence_reference

            FROM registry_books b

            INNER JOIN registry_book_directions bd
                ON bd.registry_book_id =
                    b.id

            INNER JOIN registry_number_sequences s
                ON s.id =
                    b.number_sequence_id

            INNER JOIN registry_periods p
                ON p.id =
                    b.registry_period_id

            INNER JOIN secretariat_desks sd
                ON sd.id =
                    b.secretariat_desk_id

            INNER JOIN secretariat_desk_organizations sdo
                ON sdo.secretariat_desk_id =
                    sd.id

               AND sdo.organization_id = ?

               AND sdo.status = 'active'

               AND (
                    sdo.valid_from IS NULL
                    OR sdo.valid_from <=
                        UTC_TIMESTAMP()
               )

               AND (
                    sdo.valid_until IS NULL
                    OR sdo.valid_until >=
                        UTC_TIMESTAMP()
               )

            INNER JOIN secretariat_desk_appointments sda
                ON sda.secretariat_desk_id =
                    sd.id

               AND sda.appointment_reference = ?

               AND sda.status = 'active'

               AND (
                    sda.valid_from IS NULL
                    OR sda.valid_from <=
                        UTC_TIMESTAMP()
               )

               AND (
                    sda.valid_until IS NULL
                    OR sda.valid_until >=
                        UTC_TIMESTAMP()
               )

            WHERE b.root_organization_id = ?
              AND b.organization_id = ?

              AND bd.direction_code = ?

              AND b.status = 'active'
              AND s.status = 'active'
              AND p.status = 'active'
              AND sd.status = 'active'

              AND sdo.{$registerColumn} = 1
              AND sd.{$supportColumn} = 1

              AND p.starts_on <= CURRENT_DATE
              AND p.ends_on >= CURRENT_DATE

              AND b.secretariat_desk_id =
                    s.secretariat_desk_id

              AND b.registry_period_id =
                    s.registry_period_id

            ORDER BY
                sda.is_primary DESC,
                b.id ASC

            LIMIT 2
        ";

        $statement =
            $pdo->prepare(
                $sql
            );

        $statement->execute([
            (int) $correspondence[
                'organization_id'
            ],

            (string) $actor[
                'appointment_reference'
            ],

            (int) $actor[
                'root_organization_id'
            ],

            (int) $correspondence[
                'organization_id'
            ],

            $direction,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function lockSequence(
        PDO $pdo,
        int $sequenceId
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT *

                FROM registry_number_sequences

                WHERE id = ?

                LIMIT 1

                FOR UPDATE
            ");

        $statement->execute([
            $sequenceId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function consumeReservation(
        PDO $pdo,
        array $correspondence,
        array $actor,
        array $book,
        array $sequence,
        int $number,
        string $formattedNumber,
        string $now
    ): int {
        $reference =
            $this->reservationReference();

        $idempotencyKey =
            'official:'
            . (string) $correspondence[
                'public_reference'
            ];

        $hasIdempotency =
            $this->reservationHasIdempotency(
                $pdo
            );

        if ($hasIdempotency) {
            $sql = "
                INSERT INTO
                    registry_number_reservations (
                        public_reference,
                        idempotency_key,

                        root_organization_id,
                        organization_id,

                        secretariat_desk_id,
                        registry_book_id,
                        number_sequence_id,

                        correspondence_id,

                        sequential_number,
                        formatted_number,

                        reservation_status_code,

                        reserved_by_user_id,
                        reserved_appointment_reference,
                        actor_context_snapshot_json,

                        reserved_at,
                        consumed_at,

                        created_at,
                        updated_at
                    )

                VALUES (
                    ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?,
                    ?, ?,
                    'consumed',
                    ?, ?, ?,
                    ?, ?,
                    ?, ?
                )
            ";

            $params = [
                $reference,
                $idempotencyKey,

                (int) $actor[
                    'root_organization_id'
                ],

                (int) $correspondence[
                    'organization_id'
                ],

                (int) $book[
                    'secretariat_desk_id'
                ],

                (int) $book[
                    'registry_book_id'
                ],

                (int) $sequence['id'],

                (int) $correspondence['id'],

                $number,
                $formattedNumber,

                (int) $actor['user_id'],

                (string) $actor[
                    'appointment_reference'
                ],

                (string) $actor[
                    'snapshot_json'
                ],

                $now,
                $now,
                $now,
                $now,
            ];

        } else {
            $sql = "
                INSERT INTO
                    registry_number_reservations (
                        public_reference,

                        root_organization_id,
                        organization_id,

                        secretariat_desk_id,
                        registry_book_id,
                        number_sequence_id,

                        correspondence_id,

                        sequential_number,
                        formatted_number,

                        reservation_status_code,

                        reserved_by_user_id,
                        reserved_appointment_reference,
                        actor_context_snapshot_json,

                        reserved_at,
                        consumed_at,

                        created_at,
                        updated_at
                    )

                VALUES (
                    ?,
                    ?, ?,
                    ?, ?, ?,
                    ?,
                    ?, ?,
                    'consumed',
                    ?, ?, ?,
                    ?, ?,
                    ?, ?
                )
            ";

            $params = [
                $reference,

                (int) $actor[
                    'root_organization_id'
                ],

                (int) $correspondence[
                    'organization_id'
                ],

                (int) $book[
                    'secretariat_desk_id'
                ],

                (int) $book[
                    'registry_book_id'
                ],

                (int) $sequence['id'],

                (int) $correspondence['id'],

                $number,
                $formattedNumber,

                (int) $actor['user_id'],

                (string) $actor[
                    'appointment_reference'
                ],

                (string) $actor[
                    'snapshot_json'
                ],

                $now,
                $now,
                $now,
                $now,
            ];
        }

        $statement =
            $pdo->prepare(
                $sql
            );

        $statement->execute(
            $params
        );

        $id =
            (int) $pdo
                ->lastInsertId();

        if ($id < 1) {
            throw new RuntimeException(
                'registry_reservation_insert_failed'
            );
        }

        return $id;
    }

    private function reservationHasIdempotency(
        PDO $pdo
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM information_schema.columns

                WHERE table_schema =
                        DATABASE()

                  AND table_name =
                        'registry_number_reservations'

                  AND column_name =
                        'idempotency_key'
            ");

        $statement->execute();

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function formatNumber(
        array $sequence,
        int $number
    ): string {
        $padding =
            max(
                1,
                (int) (
                    $sequence[
                        'number_padding'
                    ] ?? 1
                )
            );

        $sequenceText =
            str_pad(
                (string) $number,
                $padding,
                '0',
                STR_PAD_LEFT
            );

        $prefix =
            (string) (
                $sequence['prefix']
                ?? ''
            );

        $suffix =
            (string) (
                $sequence['suffix']
                ?? ''
            );

        $pattern =
            trim(
                (string) (
                    $sequence[
                        'format_pattern'
                    ]
                    ?? ''
                )
            );

        if ($pattern === '') {
            $pattern =
                '{prefix}{sequence}{suffix}';
        }

        return
            trim(
                str_replace(
                    [
                        '{prefix}',
                        '{sequence}',
                        '{suffix}',
                    ],
                    [
                        $prefix,
                        $sequenceText,
                        $suffix,
                    ],
                    $pattern
                )
            );
    }

    private function reservationReference(): string
    {
        return
            'RNR-'
            . gmdate('YmdHis')
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(5)
                )
            );
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
