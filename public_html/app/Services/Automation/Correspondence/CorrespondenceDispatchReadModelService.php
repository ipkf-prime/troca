<?php

namespace App\Services\Automation\Correspondence;

use PDO;

/**
 * Read-only operator model for correspondence dispatch state.
 *
 * This service deliberately contains no lifecycle operation,
 * provider invocation, network access, or database mutation.
 */
final class CorrespondenceDispatchReadModelService
{
    private AutomationOperationalRuntime $runtime;

    public function __construct(
        ?AutomationOperationalRuntime $runtime = null
    ) {
        $this->runtime =
            $runtime
            ?? new AutomationOperationalRuntime();
    }

    public function forCorrespondence(
        string $publicReference
    ): array {
        $publicReference =
            trim($publicReference);

        if ($publicReference === '') {
            return $this->unavailable(
                'invalid_correspondence_reference'
            );
        }

        $db =
            $this->runtime->connection();

        $statement =
            $db->prepare("
                SELECT
                    id,
                    public_reference,
                    direction_code,
                    status_code

                FROM correspondences

                WHERE public_reference = ?

                LIMIT 1
            ");

        $statement->execute([
            $publicReference,
        ]);

        $correspondence =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($correspondence)) {
            return $this->unavailable(
                'correspondence_not_found'
            );
        }

        $correspondenceId =
            (int) $correspondence['id'];


        /*
         * Current primary recipients.
         */
        $statement =
            $db->prepare("
                SELECT
                    id,
                    target_kind_code,
                    external_display_name,
                    external_organization_name,
                    external_contact_or_address,
                    sort_order

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

        $parties =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];


        /*
         * Recipient-level dispatch requests.
         */
        $statement =
            $db->prepare("
                SELECT
                    id,
                    public_reference,
                    correspondence_party_id,
                    channel_code,
                    status_code,
                    tracking_code,
                    provider_reference,
                    requested_at,
                    dispatched_at,
                    delivered_at,
                    failed_at,
                    cancelled_at,
                    failure_code,
                    failure_message

                FROM correspondence_dispatches

                WHERE correspondence_id = ?

                ORDER BY id
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        $dispatchRows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];


        /*
         * Attempts are read in one query to avoid N+1.
         */
        $dispatchIds = [];

        foreach ($dispatchRows as $dispatch) {
            $id =
                (int) (
                    $dispatch['id']
                    ?? 0
                );

            if ($id > 0) {
                $dispatchIds[] = $id;
            }
        }

        $attemptRows = [];

        if ($dispatchIds !== []) {
            $placeholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($dispatchIds),
                        '?'
                    )
                );

            $statement =
                $db->prepare("
                    SELECT
                        id,
                        dispatch_id,
                        attempt_number,
                        provider_code,
                        provider_reference,
                        status_code,
                        requested_at,
                        completed_at,
                        failure_code,
                        failure_message

                    FROM correspondence_dispatch_attempts

                    WHERE dispatch_id IN (
                        {$placeholders}
                    )

                    ORDER BY
                        dispatch_id,
                        attempt_number,
                        id
                ");

            $statement->execute(
                $dispatchIds
            );

            $attemptRows =
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];
        }


        $attemptsByDispatch = [];

        foreach ($attemptRows as $attempt) {
            $dispatchId =
                (int) (
                    $attempt['dispatch_id']
                    ?? 0
                );

            if ($dispatchId < 1) {
                continue;
            }

            $attemptsByDispatch[
                $dispatchId
            ][] = $attempt;
        }


        $dispatchesByParty = [];
        $unassignedDispatches = [];

        foreach ($dispatchRows as $dispatch) {
            $dispatchId =
                (int) (
                    $dispatch['id']
                    ?? 0
                );

            $attempts =
                $attemptsByDispatch[
                    $dispatchId
                ] ?? [];

            $item =
                $this->dispatch(
                    $dispatch,
                    $attempts
                );

            $partyId =
                (int) (
                    $dispatch[
                        'correspondence_party_id'
                    ] ?? 0
                );

            if ($partyId > 0) {
                $dispatchesByParty[
                    $partyId
                ][] = $item;

                continue;
            }

            $unassignedDispatches[] =
                $item;
        }


        $recipients = [];

        foreach ($parties as $party) {
            $partyId =
                (int) (
                    $party['id']
                    ?? 0
                );

            $recipients[] = [
                'party_id' =>
                    $partyId,

                'display' =>
                    $this->recipientDisplay(
                        $party
                    ),

                'organization' =>
                    trim(
                        (string) (
                            $party[
                                'external_organization_name'
                            ] ?? ''
                        )
                    ),

                'contact' =>
                    trim(
                        (string) (
                            $party[
                                'external_contact_or_address'
                            ] ?? ''
                        )
                    ),

                'target_kind_code' =>
                    trim(
                        (string) (
                            $party[
                                'target_kind_code'
                            ] ?? ''
                        )
                    ),

                'dispatches' =>
                    $dispatchesByParty[
                        $partyId
                    ] ?? [],
            ];
        }


        return [
            'available' =>
                true,

            'error' =>
                '',

            'correspondence' => [
                'public_reference' =>
                    (string) $correspondence[
                        'public_reference'
                    ],

                'direction_code' =>
                    (string) $correspondence[
                        'direction_code'
                    ],

                'status_code' =>
                    (string) $correspondence[
                        'status_code'
                    ],
            ],

            'recipient_count' =>
                count($recipients),

            'dispatch_count' =>
                count($dispatchRows),

            'attempt_count' =>
                count($attemptRows),

            'unassigned_dispatch_count' =>
                count(
                    $unassignedDispatches
                ),

            'recipients' =>
                $recipients,

            'unassigned_dispatches' =>
                $unassignedDispatches,
        ];
    }


    private function dispatch(
        array $row,
        array $attempts
    ): array {
        $latestAttempt = null;

        if ($attempts !== []) {
            $latestAttempt =
                $attempts[
                    array_key_last(
                        $attempts
                    )
                ];
        }

        $statusCode =
            trim(
                (string) (
                    $row['status_code']
                    ?? ''
                )
            );

        $latestStatus =
            is_array($latestAttempt)
                ? trim(
                    (string) (
                        $latestAttempt[
                            'status_code'
                        ] ?? ''
                    )
                )
                : '';

        return [
            'public_reference' =>
                (string) (
                    $row[
                        'public_reference'
                    ] ?? ''
                ),

            'channel_code' =>
                (string) (
                    $row[
                        'channel_code'
                    ] ?? ''
                ),

            'channel_label' =>
                $this->channelLabel(
                    (string) (
                        $row[
                            'channel_code'
                        ] ?? ''
                    )
                ),

            'status_code' =>
                $statusCode,

            'status_label' =>
                $this->dispatchStatusLabel(
                    $statusCode
                ),

            'requested_at' =>
                $row['requested_at']
                    ?? null,

            'dispatched_at' =>
                $row['dispatched_at']
                    ?? null,

            'delivered_at' =>
                $row['delivered_at']
                    ?? null,

            'failed_at' =>
                $row['failed_at']
                    ?? null,

            'cancelled_at' =>
                $row['cancelled_at']
                    ?? null,

            'tracking_code' =>
                trim(
                    (string) (
                        $row[
                            'tracking_code'
                        ] ?? ''
                    )
                ),

            'provider_reference' =>
                trim(
                    (string) (
                        $row[
                            'provider_reference'
                        ] ?? ''
                    )
                ),

            'failure_code' =>
                trim(
                    (string) (
                        $row[
                            'failure_code'
                        ] ?? ''
                    )
                ),

            'failure_message' =>
                trim(
                    (string) (
                        $row[
                            'failure_message'
                        ] ?? ''
                    )
                ),

            'attempt_count' =>
                count($attempts),

            'latest_attempt' =>
                is_array($latestAttempt)
                    ? $this->attempt(
                        $latestAttempt
                    )
                    : null,

            'retryable' =>
                $statusCode === 'pending'
                && $latestStatus === 'failed',

            'needs_review' =>
                $statusCode === 'pending'
                && $latestStatus === 'uncertain',
        ];
    }


    private function attempt(
        array $row
    ): array {
        $statusCode =
            trim(
                (string) (
                    $row['status_code']
                    ?? ''
                )
            );

        return [
            'number' =>
                (int) (
                    $row[
                        'attempt_number'
                    ] ?? 0
                ),

            'status_code' =>
                $statusCode,

            'status_label' =>
                $this->attemptStatusLabel(
                    $statusCode
                ),

            'provider_code' =>
                trim(
                    (string) (
                        $row[
                            'provider_code'
                        ] ?? ''
                    )
                ),

            'provider_reference' =>
                trim(
                    (string) (
                        $row[
                            'provider_reference'
                        ] ?? ''
                    )
                ),

            'requested_at' =>
                $row['requested_at']
                    ?? null,

            'completed_at' =>
                $row['completed_at']
                    ?? null,

            'failure_code' =>
                trim(
                    (string) (
                        $row[
                            'failure_code'
                        ] ?? ''
                    )
                ),

            'failure_message' =>
                trim(
                    (string) (
                        $row[
                            'failure_message'
                        ] ?? ''
                    )
                ),
        ];
    }


    private function recipientDisplay(
        array $party
    ): string {
        foreach ([
            'external_display_name',
            'external_organization_name',
            'external_contact_or_address',
        ] as $field) {
            $value =
                trim(
                    (string) (
                        $party[$field]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }

        $id =
            (int) (
                $party['id']
                ?? 0
            );

        return $id > 0
            ? 'گیرنده شماره ' . $id
            : 'گیرنده اصلی';
    }


    private function channelLabel(
        string $code
    ): string {
        return [
            'postal' =>
                'پست',

            'courier' =>
                'پیک',

            'hand_delivery' =>
                'تحویل دستی',

            'fax' =>
                'فاکس',

            'email' =>
                'رایانامه',

            'system' =>
                'سامانه',
        ][$code] ?? (
            $code !== ''
                ? $code
                : 'نامشخص'
        );
    }


    private function dispatchStatusLabel(
        string $code
    ): string {
        return [
            'pending' =>
                'در انتظار ارسال',

            'processing' =>
                'در حال پردازش',

            'dispatched' =>
                'ارسال شده',

            'delivered' =>
                'تحویل شده',

            'failed' =>
                'ناموفق',

            'cancelled' =>
                'لغو شده',
        ][$code] ?? (
            $code !== ''
                ? $code
                : 'نامشخص'
        );
    }


    private function attemptStatusLabel(
        string $code
    ): string {
        return [
            'pending' =>
                'در انتظار',

            'processing' =>
                'در حال ارسال',

            'succeeded' =>
                'موفق',

            'failed' =>
                'ناموفق — قابل تلاش مجدد',

            'uncertain' =>
                'وضعیت نامشخص — نیازمند بررسی',
        ][$code] ?? (
            $code !== ''
                ? $code
                : 'بدون تلاش'
        );
    }


    private function unavailable(
        string $error
    ): array {
        return [
            'available' =>
                false,

            'error' =>
                $error,

            'recipient_count' =>
                0,

            'dispatch_count' =>
                0,

            'attempt_count' =>
                0,

            'unassigned_dispatch_count' =>
                0,

            'recipients' =>
                [],

            'unassigned_dispatches' =>
                [],
        ];
    }
}
