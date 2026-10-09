<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\AuthorizationService;
use App\Services\IdentityNormalizer;
use App\Services\Organization\OrganizationalAffiliationService;
use App\Services\UserInvitationService;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Logging\DatabaseAuditLogger;
use IPKF\Logging\RequestContext;
use PDO;
use RuntimeException;
use Throwable;

final class TicketingBulkOnboardingService
{
    private PDO $core;
    private PDO $ticketing;
    private IdentityNormalizer $normalizer;
    private UserInvitationService $invitations;
    private OrganizationalAffiliationService $affiliations;
    private TicketRequesterOnboardingService $onboarding;
    private DatabaseAuditLogger $audit;
    private AuthorizationService $authorization;

    public function __construct(
        ?ConnectionResolver $resolver = null,
        ?IdentityNormalizer $normalizer = null,
        ?UserInvitationService $invitations = null,
        ?OrganizationalAffiliationService $affiliations = null,
        ?TicketRequesterOnboardingService $onboarding = null,
        ?DatabaseAuditLogger $audit = null,
        ?AuthorizationService $authorization = null
    ) {
        $resolver ??= new ConnectionResolver();

        $this->core = $resolver->resolve('core.primary');
        $this->ticketing = $resolver->resolve('ticketing.primary');
        $this->normalizer = $normalizer ?? new IdentityNormalizer();
        $this->invitations = $invitations ?? new UserInvitationService();
        $this->affiliations = $affiliations ?? new OrganizationalAffiliationService();
        $this->onboarding = $onboarding ?? new TicketRequesterOnboardingService($resolver);
        $this->audit = $audit ?? new DatabaseAuditLogger($this->core);
        $this->authorization = $authorization ?? new AuthorizationService();
    }

    public function run(
        string $filePath,
        string $mode,
        int $actorUserId,
        string $projectReference,
        int $inviteExpiryDays = 7
    ): array {
        $mode = strtolower(trim($mode));
        $projectReference = trim($projectReference);
        $inviteExpiryDays = max(1, min(30, $inviteExpiryDays));

        if (!in_array($mode, ['dry-run', 'apply'], true)) {
            throw new RuntimeException('bulk_onboarding_mode_invalid');
        }

        if ($actorUserId < 1) {
            throw new RuntimeException('bulk_onboarding_actor_required');
        }

        if (
            !$this->authorization->hasPermission($actorUserId, 'users.create')
            && !$this->authorization->hasPermission($actorUserId, 'users.manage')
        ) {
            throw new RuntimeException('bulk_onboarding_user_management_forbidden');
        }

        $managerGate = $this->onboarding->membershipRequestsForManager(
            $projectReference,
            $actorUserId
        );

        if (($managerGate['ok'] ?? false) !== true) {
            throw new RuntimeException(
                (string)($managerGate['state'] ?? 'bulk_onboarding_project_management_forbidden')
            );
        }

        $project = $this->projectByReference($projectReference);
        if ($project === null) {
            throw new RuntimeException('bulk_onboarding_project_not_found');
        }

        $file = $this->readCsv($filePath);
        $batchReference = $this->batchReference();
        $fileSha256 = hash_file('sha256', $filePath);
        if (!is_string($fileSha256) || strlen($fileSha256) !== 64) {
            throw new RuntimeException('bulk_onboarding_file_hash_failed');
        }

        RequestContext::setActor(
            $actorUserId,
            'user:' . $actorUserId,
            'user'
        );

        $batchId = $this->createBatch(
            $batchReference,
            basename($filePath),
            $fileSha256,
            count($file['rows']),
            $actorUserId,
            $mode
        );

        $this->audit->record(
            'Ticketing.BulkOnboarding.BatchStarted',
            [
                'module' => 'ticketing',
                'action' => $mode,
                'actor_user_id' => $actorUserId,
                'actor_user_reference' => 'user:' . $actorUserId,
                'target_type' => 'bulk_onboarding_batch',
                'target_id' => (string)$batchId,
                'target_reference' => $batchReference,
                'result' => 'started',
                'metadata' => [
                    'project_reference' => $projectReference,
                    'file_name' => basename($filePath),
                    'file_sha256' => $fileSha256,
                    'row_count' => count($file['rows']),
                    'mode' => $mode,
                ],
            ]
        );

        $summary = [
            'batch_id' => $batchId,
            'batch_reference' => $batchReference,
            'mode' => $mode,
            'project_reference' => $projectReference,
            'file_name' => basename($filePath),
            'file_sha256' => $fileSha256,
            'total_rows' => count($file['rows']),
            'valid_rows' => 0,
            'imported_rows' => 0,
            'duplicate_rows' => 0,
            'failed_rows' => 0,
            'results' => [],
        ];

        $seenMobiles = [];

        try {
            foreach ($file['rows'] as $row) {
                $sourceRowNumber = (int)$row['source_row_number'];
                $raw = (array)$row['payload'];
                $normalized = null;
                $result = null;

                try {
                    $normalized = $this->normalizeRow($raw);
                    $mobile = $normalized['mobile'];

                    if (isset($seenMobiles[$mobile])) {
                        $result = [
                            'result_code' => 'duplicate_in_file',
                            'participant_id' => null,
                            'error_code' => null,
                            'user_id' => null,
                            'organization_membership_reference' => null,
                            'member_id' => null,
                            'invitation_reference' => null,
                            'invitation_url' => null,
                        ];

                        $summary['duplicate_rows']++;
                    } else {
                        $seenMobiles[$mobile] = $sourceRowNumber;
                        $summary['valid_rows']++;
                        $result = $this->processRow(
                            $normalized,
                            $mode,
                            $actorUserId,
                            $projectReference,
                            (int)$project['id'],
                            $batchReference,
                            $inviteExpiryDays
                        );

                        if (
                            in_array(
                                $result['result_code'],
                                [
                                    'onboarded',
                                    'invited',
                                ],
                                true
                            )
                        ) {
                            $summary['imported_rows']++;
                        } elseif (
                            in_array(
                                $result['result_code'],
                                [
                                    'already_onboarded',
                                    'already_invited',
                                ],
                                true
                            )
                        ) {
                            $summary['duplicate_rows']++;
                        }
                    }

                    $this->recordRow(
                        $batchId,
                        $sourceRowNumber,
                        $raw,
                        $normalized,
                        $result['result_code'],
                        null,
                        $result['participant_id']
                    );

                    $this->auditRow(
                        $batchReference,
                        $sourceRowNumber,
                        $actorUserId,
                        $projectReference,
                        $mode,
                        $normalized,
                        $result
                    );

                } catch (Throwable $exception) {
                    $summary['failed_rows']++;
                    $errorCode = $this->safeErrorCode($exception->getMessage());

                    $result = [
                        'result_code' => 'failed',
                        'participant_id' => null,
                        'error_code' => $errorCode,
                        'user_id' => null,
                        'organization_membership_reference' => null,
                        'member_id' => null,
                        'invitation_reference' => null,
                        'invitation_url' => null,
                    ];

                    $this->recordRow(
                        $batchId,
                        $sourceRowNumber,
                        $raw,
                        $normalized,
                        'failed',
                        [
                            'code' => $errorCode,
                            'message' => mb_substr($exception->getMessage(), 0, 500),
                        ],
                        null
                    );

                    $this->audit->record(
                        'Ticketing.BulkOnboarding.RowFailed',
                        [
                            'module' => 'ticketing',
                            'action' => $mode,
                            'actor_user_id' => $actorUserId,
                            'actor_user_reference' => 'user:' . $actorUserId,
                            'target_type' => 'bulk_onboarding_row',
                            'target_id' => $batchReference . ':' . $sourceRowNumber,
                            'target_reference' => $batchReference,
                            'result' => 'failed',
                            'reason' => $errorCode,
                            'metadata' => [
                                'project_reference' => $projectReference,
                                'source_row_number' => $sourceRowNumber,
                                'mobile_sha256' => is_array($normalized)
                                    ? hash('sha256', (string)($normalized['mobile'] ?? ''))
                                    : null,
                                'organization_reference' => is_array($normalized)
                                    ? ($normalized['organization_reference'] ?? null)
                                    : null,
                            ],
                        ]
                    );
                }

                $summary['results'][] = [
                    'source_row_number' => $sourceRowNumber,
                    'mobile' => is_array($normalized)
                        ? (string)($normalized['mobile'] ?? '')
                        : trim((string)($raw['mobile'] ?? '')),
                    'organization_reference' => is_array($normalized)
                        ? (string)($normalized['organization_reference'] ?? '')
                        : trim((string)($raw['organization_reference'] ?? '')),
                    'result_code' => (string)($result['result_code'] ?? 'failed'),
                    'user_id' => $result['user_id'] ?? null,
                    'organization_membership_reference' =>
                        $result['organization_membership_reference'] ?? null,
                    'member_id' => $result['member_id'] ?? null,
                    'invitation_reference' => $result['invitation_reference'] ?? null,
                    'invitation_url' => $result['invitation_url'] ?? null,
                    'error_code' => $result['error_code'] ?? null,
                ];
            }

            $status = $this->batchStatus($mode, $summary['failed_rows']);
            $this->completeBatch($batchId, $summary, $status);

            $this->audit->record(
                'Ticketing.BulkOnboarding.BatchCompleted',
                [
                    'module' => 'ticketing',
                    'action' => $mode,
                    'actor_user_id' => $actorUserId,
                    'actor_user_reference' => 'user:' . $actorUserId,
                    'target_type' => 'bulk_onboarding_batch',
                    'target_id' => (string)$batchId,
                    'target_reference' => $batchReference,
                    'result' => $status,
                    'after' => [
                        'total_rows' => $summary['total_rows'],
                        'valid_rows' => $summary['valid_rows'],
                        'imported_rows' => $summary['imported_rows'],
                        'duplicate_rows' => $summary['duplicate_rows'],
                        'failed_rows' => $summary['failed_rows'],
                    ],
                    'metadata' => [
                        'project_reference' => $projectReference,
                        'file_sha256' => $fileSha256,
                        'mode' => $mode,
                    ],
                ]
            );

            $summary['status_code'] = $status;
            return $summary;

        } catch (Throwable $exception) {
            $this->failBatch($batchId);

            try {
                $this->audit->record(
                    'Ticketing.BulkOnboarding.BatchFailed',
                    [
                        'module' => 'ticketing',
                        'action' => $mode,
                        'actor_user_id' => $actorUserId,
                        'actor_user_reference' => 'user:' . $actorUserId,
                        'target_type' => 'bulk_onboarding_batch',
                        'target_id' => (string)$batchId,
                        'target_reference' => $batchReference,
                        'result' => 'failed',
                        'reason' => $this->safeErrorCode($exception->getMessage()),
                        'metadata' => [
                            'project_reference' => $projectReference,
                            'file_sha256' => $fileSha256,
                            'mode' => $mode,
                        ],
                    ]
                );
            } catch (Throwable) {
                // Preserve the primary failure.
            }

            throw $exception;
        }
    }

    private function processRow(
        array $row,
        string $mode,
        int $actorUserId,
        string $projectReference,
        int $projectId,
        string $batchReference,
        int $inviteExpiryDays
    ): array {
        if (isset($row['__parse_error'])) {
            throw new RuntimeException('bulk_onboarding_csv_column_count_mismatch');
        }

        $organization = $this->organizationByReference(
            $row['organization_reference']
        );

        if ($organization === null) {
            throw new RuntimeException('bulk_onboarding_organization_invalid');
        }

        $user = $this->coreUserByMobile($row['mobile']);

        if ($user === null) {
            return $this->processNewUser(
                $row,
                $mode,
                $actorUserId,
                $projectReference,
                $batchReference,
                $inviteExpiryDays
            );
        }

        if ((string)($user['status'] ?? '') !== 'active') {
            throw new RuntimeException('bulk_onboarding_user_inactive');
        }

        if (trim((string)($user['person_reference'] ?? '')) === '') {
            throw new RuntimeException('bulk_onboarding_person_reference_missing');
        }

        $activeMember = $this->activeProjectMember(
            $projectId,
            (int)$user['id']
        );

        if ($activeMember !== null) {
            $boundOrganization = trim(
                (string)($activeMember['organization_reference'] ?? '')
            );

            if (
                $boundOrganization === $row['organization_reference']
                && trim((string)($activeMember['core_organization_membership_reference'] ?? '')) !== ''
            ) {
                return [
                    'result_code' => 'already_onboarded',
                    'participant_id' => $this->nullablePositiveInt(
                        $activeMember['participant_id'] ?? null
                    ),
                    'error_code' => null,
                    'user_id' => (int)$user['id'],
                    'organization_membership_reference' =>
                        (string)$activeMember['core_organization_membership_reference'],
                    'member_id' => (int)$activeMember['id'],
                    'invitation_reference' => null,
                    'invitation_url' => null,
                ];
            }

            throw new RuntimeException(
                'bulk_onboarding_active_membership_conflict'
            );
        }

        $verifiedAffiliation = $this->verifiedAffiliation(
            (int)$user['id'],
            $row['organization_reference']
        );

        if ($mode === 'dry-run') {
            return [
                'result_code' => $verifiedAffiliation !== null
                    ? 'would_join'
                    : 'would_onboard',
                'participant_id' => null,
                'error_code' => null,
                'user_id' => (int)$user['id'],
                'organization_membership_reference' =>
                    $verifiedAffiliation['public_reference'] ?? null,
                'member_id' => null,
                'invitation_reference' => null,
                'invitation_url' => null,
            ];
        }

        $membershipReference = $verifiedAffiliation !== null
            ? (string)$verifiedAffiliation['public_reference']
            : $this->createAndApproveAffiliation(
                $projectReference,
                (int)$user['id'],
                $row,
                $actorUserId
            );

        $join = $this->onboarding->joinOpen(
            $projectReference,
            (int)$user['id'],
            $membershipReference
        );

        if (($join['ok'] ?? false) !== true) {
            throw new RuntimeException(
                (string)($join['error'] ?? 'bulk_onboarding_join_failed')
            );
        }

        $membership = is_array($join['membership'] ?? null)
            ? $join['membership']
            : [];

        $memberId = (int)($membership['id'] ?? 0);
        $state = (string)($membership['state'] ?? '');

        if ($state === 'pending_approval') {
            $requestReference = trim(
                (string)($membership['request_reference'] ?? '')
            );

            if ($requestReference === '') {
                throw new RuntimeException(
                    'bulk_onboarding_membership_request_reference_missing'
                );
            }

            $decision = $this->onboarding->decideMembershipRequest(
                $projectReference,
                $requestReference,
                'approve',
                $actorUserId,
                'bulk_onboarding:' . $batchReference
            );

            if (($decision['ok'] ?? false) !== true) {
                throw new RuntimeException(
                    (string)($decision['state'] ?? 'bulk_onboarding_membership_approval_failed')
                );
            }

            $memberId = (int)($decision['member_id'] ?? 0);
        }

        if ($memberId < 1) {
            $member = $this->activeProjectMember(
                $projectId,
                (int)$user['id']
            );
            $memberId = (int)($member['id'] ?? 0);
        }

        $member = $this->activeProjectMember(
            $projectId,
            (int)$user['id']
        );

        if (
            $member === null
            || trim((string)($member['organization_reference'] ?? ''))
                !== $row['organization_reference']
            || trim((string)($member['core_organization_membership_reference'] ?? ''))
                !== $membershipReference
        ) {
            throw new RuntimeException(
                'bulk_onboarding_membership_postcondition_failed'
            );
        }

        return [
            'result_code' => 'onboarded',
            'participant_id' => $this->nullablePositiveInt(
                $member['participant_id'] ?? null
            ),
            'error_code' => null,
            'user_id' => (int)$user['id'],
            'organization_membership_reference' => $membershipReference,
            'member_id' => (int)$member['id'],
            'invitation_reference' => null,
            'invitation_url' => null,
        ];
    }

    private function processNewUser(
        array $row,
        string $mode,
        int $actorUserId,
        string $projectReference,
        string $batchReference,
        int $inviteExpiryDays
    ): array {
        $pending = $this->pendingInvitationByMobile($row['mobile']);

        if ($pending !== null) {
            return [
                'result_code' => 'already_invited',
                'participant_id' => null,
                'error_code' => null,
                'user_id' => null,
                'organization_membership_reference' => null,
                'member_id' => null,
                'invitation_reference' => (string)$pending['public_reference'],
                'invitation_url' => null,
            ];
        }

        if ($mode === 'dry-run') {
            return [
                'result_code' => 'would_invite',
                'participant_id' => null,
                'error_code' => null,
                'user_id' => null,
                'organization_membership_reference' => null,
                'member_id' => null,
                'invitation_reference' => null,
                'invitation_url' => null,
            ];
        }

        $created = $this->invitations->create(
            $actorUserId,
            [
                'full_name' => $row['full_name'],
                'mobile' => $row['mobile'],
                'email' => $row['email'],
                'expires_days' => $inviteExpiryDays,
                'created_ip' => '127.0.0.1',
                'created_user_agent' => 'IPKF-Ticketing-Bulk-Onboarding',
            ]
        );

        if (($created['ok'] ?? false) !== true) {
            throw new RuntimeException(
                $this->invitationError($created)
            );
        }

        $invitation = is_array($created['invitation'] ?? null)
            ? $created['invitation']
            : [];

        $reference = trim(
            (string)($invitation['public_reference'] ?? '')
        );
        $url = trim((string)($invitation['url'] ?? ''));

        if ($reference === '' || $url === '') {
            throw new RuntimeException(
                'bulk_onboarding_invitation_result_invalid'
            );
        }

        $this->audit->record(
            'Ticketing.BulkOnboarding.InvitationCreated',
            [
                'module' => 'ticketing',
                'action' => 'invite',
                'actor_user_id' => $actorUserId,
                'actor_user_reference' => 'user:' . $actorUserId,
                'target_type' => 'user_invitation',
                'target_reference' => $reference,
                'result' => 'created',
                'metadata' => [
                    'batch_reference' => $batchReference,
                    'project_reference' => $projectReference,
                    'organization_reference' => $row['organization_reference'],
                    'mobile_sha256' => hash('sha256', $row['mobile']),
                    'continuation_required' => true,
                    'continuation' => 'register_then_rerun_bulk_onboarding',
                ],
            ]
        );

        return [
            'result_code' => 'invited',
            'participant_id' => null,
            'error_code' => null,
            'user_id' => null,
            'organization_membership_reference' => null,
            'member_id' => null,
            'invitation_reference' => $reference,
            'invitation_url' => $url,
        ];
    }

    private function createAndApproveAffiliation(
        string $projectReference,
        int $userId,
        array $row,
        int $actorUserId
    ): string {
        $request = $this->onboarding->requestAffiliation(
            $projectReference,
            $userId,
            [
                'organization_reference' => $row['organization_reference'],
                'position_reference' => $row['position_reference'],
                'is_primary' => $row['is_primary'],
            ]
        );

        if (($request['ok'] ?? false) !== true) {
            throw new RuntimeException(
                (string)($request['state'] ?? 'bulk_onboarding_affiliation_request_failed')
            );
        }

        $reference = trim(
            (string)($request['membership_reference'] ?? '')
        );

        if ($reference === '') {
            throw new RuntimeException(
                'bulk_onboarding_affiliation_reference_missing'
            );
        }

        $decision = $this->affiliations->decide(
            $actorUserId,
            $reference,
            'approve',
            0,
            false,
            '127.0.0.1'
        );

        if ((string)($decision['status'] ?? '') !== 'approved') {
            throw new RuntimeException(
                'bulk_onboarding_affiliation_approval_failed'
            );
        }

        return $reference;
    }

    private function normalizeRow(array $row): array
    {
        $mobileRaw = trim((string)($row['mobile'] ?? ''));
        $mobile = $this->normalizer->mobile($mobileRaw);
        if ($mobile === null) {
            throw new RuntimeException('bulk_onboarding_mobile_invalid');
        }

        $organizationReference = trim(
            (string)($row['organization_reference'] ?? '')
        );
        if (
            $organizationReference === ''
            || strlen($organizationReference) > 100
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $organizationReference) !== 1
        ) {
            throw new RuntimeException(
                'bulk_onboarding_organization_reference_invalid'
            );
        }

        $emailRaw = trim((string)($row['email'] ?? ''));
        $email = $emailRaw === ''
            ? ''
            : $this->normalizer->email($emailRaw);
        if ($emailRaw !== '' && $email === null) {
            throw new RuntimeException('bulk_onboarding_email_invalid');
        }

        $fullName = preg_replace(
            '/\s+/u',
            ' ',
            trim((string)($row['full_name'] ?? ''))
        );
        $fullName = is_string($fullName) ? $fullName : '';
        if (mb_strlen($fullName, 'UTF-8') > 150) {
            throw new RuntimeException('bulk_onboarding_full_name_too_long');
        }

        $positionReference = trim(
            (string)($row['position_reference'] ?? '')
        );
        if (
            $positionReference !== ''
            && (
                strlen($positionReference) > 100
                || preg_match('/^[A-Za-z0-9._:-]+$/D', $positionReference) !== 1
            )
        ) {
            throw new RuntimeException(
                'bulk_onboarding_position_reference_invalid'
            );
        }

        return [
            'mobile' => $mobile,
            'organization_reference' => $organizationReference,
            'full_name' => $fullName,
            'email' => $email ?? '',
            'position_reference' => $positionReference,
            'is_primary' => $this->booleanValue($row['is_primary'] ?? null),
        ];
    }

    private function readCsv(string $filePath): array
    {
        $filePath = trim($filePath);
        if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('bulk_onboarding_file_unreadable');
        }

        $size = filesize($filePath);
        if ($size === false || $size < 1 || $size > 10 * 1024 * 1024) {
            throw new RuntimeException('bulk_onboarding_file_size_invalid');
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('bulk_onboarding_file_open_failed');
        }

        try {
            $firstLine = fgets($handle);
            if (!is_string($firstLine)) {
                throw new RuntimeException('bulk_onboarding_header_missing');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            $headers = str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', '\\');
            if ($headers === []) {
                throw new RuntimeException('bulk_onboarding_header_missing');
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]) ?? '';
            $headers = array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                $headers
            );

            $allowed = [
                'mobile',
                'organization_reference',
                'full_name',
                'email',
                'position_reference',
                'is_primary',
            ];

            if (
                count($headers) !== count(array_unique($headers))
                || !in_array('mobile', $headers, true)
                || !in_array('organization_reference', $headers, true)
            ) {
                throw new RuntimeException('bulk_onboarding_header_invalid');
            }

            foreach ($headers as $header) {
                if (!in_array($header, $allowed, true)) {
                    throw new RuntimeException(
                        'bulk_onboarding_header_unknown:' . $header
                    );
                }
            }

            $rows = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $rowNumber++;

                $hasValue = false;
                foreach ($values as $value) {
                    if (trim((string)$value) !== '') {
                        $hasValue = true;
                        break;
                    }
                }
                if (!$hasValue) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    $rows[] = [
                        'source_row_number' => $rowNumber,
                        'payload' => [
                            'mobile' => '',
                            'organization_reference' => '',
                            '__parse_error' => 'column_count_mismatch',
                        ],
                    ];
                    continue;
                }

                $payload = array_combine($headers, $values);
                if (!is_array($payload)) {
                    throw new RuntimeException('bulk_onboarding_row_combine_failed');
                }

                $rows[] = [
                    'source_row_number' => $rowNumber,
                    'payload' => $payload,
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('bulk_onboarding_no_data_rows');
            }

            if (count($rows) > 50000) {
                throw new RuntimeException('bulk_onboarding_row_limit_exceeded');
            }

            return [
                'delimiter' => $delimiter,
                'headers' => $headers,
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $best = ',';
        $bestCount = 0;

        foreach ($candidates as $candidate) {
            $count = count(str_getcsv(rtrim($line, "\r\n"), $candidate, '"', '\\'));
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function createBatch(
        string $reference,
        string $fileName,
        string $sha256,
        int $totalRows,
        int $actorUserId,
        string $mode
    ): int {
        $statement = $this->ticketing->prepare(
            "INSERT INTO ticketing_participant_import_batches (
                public_reference,
                file_name,
                file_sha256,
                status_code,
                total_rows,
                valid_rows,
                imported_rows,
                duplicate_rows,
                failed_rows,
                imported_by_user_reference,
                started_at,
                created_at,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );

        $statement->execute([
            $reference,
            mb_substr($fileName, 0, 255),
            $sha256,
            $mode === 'dry-run' ? 'dry_run' : 'running',
            $totalRows,
            'user:' . $actorUserId,
        ]);

        $id = (int)$this->ticketing->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException('bulk_onboarding_batch_insert_failed');
        }

        return $id;
    }

    private function recordRow(
        int $batchId,
        int $sourceRowNumber,
        array $raw,
        ?array $normalized,
        string $resultCode,
        ?array $error,
        ?int $participantId
    ): void {
        $statement = $this->ticketing->prepare(
            "INSERT INTO ticketing_participant_import_rows (
                batch_id,
                source_row_number,
                raw_payload_json,
                normalized_payload_json,
                result_code,
                error_json,
                participant_id,
                created_at,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );

        $statement->execute([
            $batchId,
            $sourceRowNumber,
            $this->json($raw),
            $normalized !== null ? $this->json($normalized) : null,
            mb_substr($resultCode, 0, 30),
            $error !== null ? $this->json($error) : null,
            $participantId,
        ]);
    }

    private function completeBatch(
        int $batchId,
        array $summary,
        string $status
    ): void {
        $statement = $this->ticketing->prepare(
            "UPDATE ticketing_participant_import_batches
             SET status_code = ?,
                 valid_rows = ?,
                 imported_rows = ?,
                 duplicate_rows = ?,
                 failed_rows = ?,
                 completed_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?"
        );

        $statement->execute([
            $status,
            (int)$summary['valid_rows'],
            (int)$summary['imported_rows'],
            (int)$summary['duplicate_rows'],
            (int)$summary['failed_rows'],
            $batchId,
        ]);
    }

    private function failBatch(int $batchId): void
    {
        $statement = $this->ticketing->prepare(
            "UPDATE ticketing_participant_import_batches
             SET status_code = 'failed',
                 completed_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?"
        );
        $statement->execute([$batchId]);
    }

    private function auditRow(
        string $batchReference,
        int $sourceRowNumber,
        int $actorUserId,
        string $projectReference,
        string $mode,
        array $normalized,
        array $result
    ): void {
        $this->audit->record(
            'Ticketing.BulkOnboarding.RowProcessed',
            [
                'module' => 'ticketing',
                'action' => $mode,
                'actor_user_id' => $actorUserId,
                'actor_user_reference' => 'user:' . $actorUserId,
                'target_type' => 'bulk_onboarding_row',
                'target_id' => $batchReference . ':' . $sourceRowNumber,
                'target_reference' => $batchReference,
                'result' => (string)$result['result_code'],
                'after' => [
                    'user_id' => $result['user_id'] ?? null,
                    'organization_membership_reference' =>
                        $result['organization_membership_reference'] ?? null,
                    'member_id' => $result['member_id'] ?? null,
                    'invitation_reference' => $result['invitation_reference'] ?? null,
                ],
                'metadata' => [
                    'project_reference' => $projectReference,
                    'source_row_number' => $sourceRowNumber,
                    'organization_reference' => $normalized['organization_reference'],
                    'mobile_sha256' => hash('sha256', $normalized['mobile']),
                    'mode' => $mode,
                ],
            ]
        );
    }

    private function projectByReference(string $reference): ?array
    {
        $statement = $this->ticketing->prepare(
            "SELECT id, public_reference, code, title
             FROM ticketing_support_projects
             WHERE public_reference = ?
               AND is_active = 1
               AND archived_at IS NULL
             LIMIT 1"
        );
        $statement->execute([$reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function organizationByReference(string $reference): ?array
    {
        $statement = $this->core->prepare(
            "SELECT id, public_reference
             FROM organizations
             WHERE public_reference = ?
               AND is_active = 1
             LIMIT 1"
        );
        $statement->execute([$reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function coreUserByMobile(string $mobile): ?array
    {
        $statement = $this->core->prepare(
            "SELECT
                users.id,
                users.person_id,
                users.status,
                users.mobile_norm,
                persons.public_reference AS person_reference,
                COALESCE(NULLIF(persons.full_name, ''), NULLIF(users.username, ''), users.mobile_norm) AS display_name
             FROM users
             LEFT JOIN persons ON persons.id = users.person_id
             WHERE users.mobile_norm = ?
               AND users.deleted_at IS NULL
             LIMIT 2"
        );
        $statement->execute([$mobile]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > 1) {
            throw new RuntimeException('bulk_onboarding_mobile_not_unique');
        }
        return $rows[0] ?? null;
    }

    private function verifiedAffiliation(
        int $userId,
        string $organizationReference
    ): ?array {
        $statement = $this->core->prepare(
            "SELECT memberships.public_reference
             FROM organization_memberships AS memberships
             INNER JOIN organizations
               ON organizations.id = memberships.organization_id
             WHERE memberships.user_id = ?
               AND organizations.public_reference = ?
               AND memberships.status = 'active'
               AND memberships.verification_state_code = 'verified'
               AND (memberships.valid_from IS NULL OR memberships.valid_from <= CURRENT_DATE)
               AND (memberships.valid_until IS NULL OR memberships.valid_until >= CURRENT_DATE)
               AND organizations.is_active = 1
             ORDER BY memberships.is_primary DESC, memberships.id DESC
             LIMIT 1"
        );
        $statement->execute([$userId, $organizationReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function activeProjectMember(
        int $projectId,
        int $userId
    ): ?array {
        $statement = $this->ticketing->prepare(
            "SELECT
                id,
                participant_id,
                role_code,
                core_organization_membership_reference,
                organization_reference
             FROM ticketing_support_project_members
             WHERE project_id = ?
               AND user_reference = ?
               AND left_at IS NULL
             LIMIT 1"
        );
        $statement->execute([$projectId, 'user:' . $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function pendingInvitationByMobile(string $mobile): ?array
    {
        $statement = $this->core->prepare(
            "SELECT public_reference
             FROM user_invitations
             WHERE mobile_norm = ?
               AND status = 'pending'
               AND accepted_at IS NULL
               AND revoked_at IS NULL
               AND expires_at >= CURRENT_TIMESTAMP
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute([$mobile]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function batchReference(): string
    {
        return 'TBO-' . strtoupper(bin2hex(random_bytes(10)));
    }

    private function batchStatus(string $mode, int $failedRows): string
    {
        if ($mode === 'dry-run') {
            return $failedRows > 0
                ? 'dry_run_with_errors'
                : 'dry_run_completed';
        }

        return $failedRows > 0
            ? 'completed_with_errors'
            : 'completed';
    }

    private function booleanValue(mixed $value): bool
    {
        $value = strtolower(trim((string)($value ?? '')));
        return in_array($value, ['1', 'true', 'yes', 'y', 'بله'], true);
    }

    private function invitationError(array $created): string
    {
        if (!empty($created['forbidden'])) {
            return 'bulk_onboarding_invitation_forbidden';
        }

        $errors = is_array($created['errors'] ?? null)
            ? $created['errors']
            : [];

        if ($errors !== []) {
            $key = (string)array_key_first($errors);
            return 'bulk_onboarding_invitation_' . preg_replace('/[^a-z0-9_]+/i', '_', $key);
        }

        return 'bulk_onboarding_invitation_failed';
    }

    private function safeErrorCode(string $message): string
    {
        $message = strtolower(trim($message));
        $message = preg_replace('/[^a-z0-9._:-]+/', '_', $message) ?? '';
        $message = trim($message, '_');
        return mb_substr(
            $message !== '' ? $message : 'bulk_onboarding_failed',
            0,
            120
        );
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        $value = (int)($value ?? 0);
        return $value > 0 ? $value : null;
    }

    private function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }
}
