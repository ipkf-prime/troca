<?php

namespace App\Services;

use App\Repositories\NotificationApprovalRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class NotificationApprovalWorkflowService extends BaseService
{
    private const CHANNELS = [
        'email',
        'sms',
        'messenger',
    ];

    public function __construct(
        private ?NotificationApprovalRepository $repository = null,
        private ?NotificationApprovalStateMachine $stateMachine = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationApprovalRepository();

        $this->stateMachine ??=
            new NotificationApprovalStateMachine();

        $this->authorization ??=
            new AuthorizationService();
    }

    public function submit(
        int $requesterUserId,
        array $snapshot
    ): array {
        if (
            !$this->authorization->hasPermission(
                $requesterUserId,
                'notifications.send.request'
            )
        ) {
            throw new RuntimeException(
                'notification_approval_request_forbidden'
            );
        }

        $this->stateMachine->assertTransition(
            NotificationApprovalStateMachine::DRAFT,
            NotificationApprovalStateMachine::PENDING
        );

        $messageType = strtolower(trim(
            (string) (
                $snapshot['message_type_code']
                ?? ''
            )
        ));

        if (
            !in_array(
                $messageType,
                ['text', 'multimedia'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'notification_approval_message_type_invalid'
            );
        }

        $channels = array_values(array_unique(
            array_filter(
                array_map(
                    static fn (
                        mixed $channel
                    ): string => strtolower(trim(
                        (string) $channel
                    )),
                    is_array(
                        $snapshot['channels']
                        ?? null
                    )
                        ? $snapshot['channels']
                        : []
                ),
                static fn (
                    string $channel
                ): bool => in_array(
                    $channel,
                    self::CHANNELS,
                    true
                )
            )
        ));

        sort($channels, SORT_STRING);

        if ($channels === []) {
            throw new InvalidArgumentException(
                'notification_approval_channel_required'
            );
        }

        $subject = trim((string) (
            $snapshot['subject'] ?? ''
        ));

        $body = trim((string) (
            $snapshot['body'] ?? ''
        ));

        if (
            mb_strlen(
                $subject,
                'UTF-8'
            ) > 500
            || $body === ''
        ) {
            throw new InvalidArgumentException(
                'notification_approval_content_invalid'
            );
        }

        $rawTargets = is_array(
            $snapshot['targets'] ?? null
        )
            ? $snapshot['targets']
            : [];

        if ($rawTargets === []) {
            throw new InvalidArgumentException(
                'notification_approval_target_required'
            );
        }

        $targets = [];

        foreach (
            $rawTargets as $index => $target
        ) {
            if (!is_array($target)) {
                continue;
            }

            $channel = strtolower(trim(
                (string) (
                    $target['channel_code']
                    ?? ''
                )
            ));

            $destination = trim((string) (
                $target['destination']
                ?? ''
            ));

            if (
                !in_array(
                    $channel,
                    $channels,
                    true
                )
                || $destination === ''
            ) {
                throw new InvalidArgumentException(
                    'notification_approval_target_invalid'
                );
            }

            $userId = (int) (
                $target['user_id']
                ?? 0
            );

            $recipientTitle = trim((string) (
                $target['recipient_title']
                ?? ''
            ));

            $masked = trim((string) (
                $target['destination_masked']
                ?? ''
            ));

            if (
                $recipientTitle === ''
                || $masked === ''
            ) {
                throw new InvalidArgumentException(
                    'notification_approval_target_invalid'
                );
            }

            $targets[] = [
                'public_reference' =>
                    'nat_' . bin2hex(
                        random_bytes(12)
                    ),

                'source_type' =>
                    $userId > 0
                        ? 'user'
                        : 'manual',

                'recipient_user_id' =>
                    $userId > 0
                        ? $userId
                        : null,

                'recipient_user_reference' =>
                    $userId > 0
                        ? 'user:' . $userId
                        : null,

                'recipient_title' =>
                    $recipientTitle,

                'channel_code' =>
                    $channel,

                'destination_snapshot' =>
                    $destination,

                'destination_masked' =>
                    $masked,

                'destination_hash' => hash(
                    'sha256',
                    $channel
                    . ':'
                    . $destination
                ),

                'sort_order' =>
                    (int) $index,

                'metadata_json' =>
                    $this->json([
                        'source_type' =>
                            $userId > 0
                                ? 'user'
                                : 'manual',
                    ]),
            ];
        }

        if ($targets === []) {
            throw new InvalidArgumentException(
                'notification_approval_target_required'
            );
        }

        $mediaAssets = is_array(
            $snapshot['media_assets']
            ?? null
        )
            ? $snapshot['media_assets']
            : [];

        $requestReason = trim((string) (
            $snapshot['request_reason']
            ?? ''
        ));

        if (
            mb_strlen(
                $requestReason,
                'UTF-8'
            ) > 1000
        ) {
            throw new InvalidArgumentException(
                'notification_approval_reason_invalid'
            );
        }

        $checksumPayload = [
            'requester_user_id' =>
                $requesterUserId,

            'message_type_code' =>
                $messageType,

            'purpose_code' =>
                'general',

            'priority_code' =>
                'normal',

            'subject' =>
                $subject,

            'body' =>
                $body,

            'channels' =>
                $channels,

            'targets' => array_map(
                static fn (
                    array $target
                ): array => [
                    'source_type' =>
                        $target[
                            'source_type'
                        ],

                    'recipient_user_id' =>
                        $target[
                            'recipient_user_id'
                        ],

                    'recipient_title' =>
                        $target[
                            'recipient_title'
                        ],

                    'channel_code' =>
                        $target[
                            'channel_code'
                        ],

                    'destination_snapshot' =>
                        $target[
                            'destination_snapshot'
                        ],
                ],
                $targets
            ),

            'media_asset_ids' =>
                array_values(array_filter(
                    array_map(
                        static fn (
                            array $asset
                        ): int => (int) (
                            $asset['id']
                            ?? 0
                        ),
                        array_filter(
                            $mediaAssets,
                            'is_array'
                        )
                    ),
                    static fn (
                        int $id
                    ): bool => $id > 0
                )),
        ];

        $payloadChecksum = hash(
            'sha256',
            $this->json(
                $checksumPayload
            )
        );

        $publicReference =
            'nar_' . bin2hex(
                random_bytes(12)
            );

        $idempotencyKey = trim(
            (string) (
                $snapshot[
                    'idempotency_key'
                ] ?? ''
            )
        );

        if (
            $idempotencyKey === ''
            || strlen(
                $idempotencyKey
            ) > 190
            || preg_match(
                '/^[A-Za-z0-9_.:-]+$/',
                $idempotencyKey
            ) !== 1
        ) {
            $idempotencyKey =
                'nari_' . bin2hex(
                    random_bytes(16)
                );
        }

        $request =
            $this->repository
                ->createPendingRequest(
                    [
                        'public_reference' =>
                            $publicReference,

                        'idempotency_key' =>
                            $idempotencyKey,

                        'requester_user_id' =>
                            $requesterUserId,

                        'requester_scope_type' =>
                            'global',

                        'requester_scope_reference' =>
                            '*',

                        'requester_context_json' =>
                            $this->json([
                                'origin' =>
                                    'notification_send_center',

                                'access_mode' =>
                                    'approval_required',
                            ]),

                        'message_type_code' =>
                            $messageType,

                        'purpose_code' =>
                            'general',

                        'priority_code' =>
                            'normal',

                        'subject' =>
                            $subject !== ''
                                ? $subject
                                : null,

                        'body' =>
                            $body,

                        'channels_json' =>
                            $this->json(
                                $channels
                            ),

                        'request_reason' =>
                            $requestReason !== ''
                                ? $requestReason
                                : null,

                        'payload_checksum_sha256' =>
                            $payloadChecksum,
                    ],
                    $targets,
                    $mediaAssets,
                    [
                        'public_reference' =>
                            'nas_' . bin2hex(
                                random_bytes(
                                    12
                                )
                            ),

                        'title' =>
                            'بررسی و تأیید ارسال اعلان',

                        'approver_rule_json' =>
                            $this->json([
                                'type' =>
                                    'permission',

                                'permission_code' =>
                                    'notifications.approvals.decide',
                            ]),
                    ]
                );

        return array_merge(
            $request,
            [
                'message_type_code' =>
                    $messageType,

                'channels' =>
                    $channels,

                'subject' =>
                    $subject,

                'media_count' =>
                    count(
                        $mediaAssets
                    ),

                'payload_checksum_sha256' =>
                    $payloadChecksum,
            ]
        );
    }

    private function json(
        array $value
    ): string {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (
            JsonException $exception
        ) {
            throw new RuntimeException(
                'notification_approval_snapshot_invalid',
                0,
                $exception
            );
        }
    }
}
