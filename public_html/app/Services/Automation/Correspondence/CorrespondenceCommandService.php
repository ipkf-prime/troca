<?php

namespace App\Services\Automation\Correspondence;

use App\Services\Automation\CoreReferenceType;
use App\Support\PersianDate;
use IPKF\Support\Clock;
use PDO;
use RuntimeException;
use Throwable;

class CorrespondenceCommandService
{
    public function __construct(
        private ?AutomationOperationalRuntime $runtime = null,
        private ?CorrespondenceRepository $correspondences = null,
        private ?CorrespondenceVersionRepository $versions = null,
        private ?CorrespondencePartyRepository $parties = null,
        private ?CorrespondenceEventRepository $events = null,
        private ?AutomationLookupRepository $lookups = null,
        private ?CorrespondenceDocumentTemplateRepository $documentTemplates = null,
        private ?CoreReferenceOptions $coreReferences = null,
        private ?CorrespondenceRelationRepository $relations = null,
        private ?EnterpriseAutomationContextService $enterpriseContext = null,
        private ?ExternalOrganizationDirectoryReferenceResolver $externalDirectory = null
    ) {
        $this->runtime ??= new AutomationOperationalRuntime();
        $this->correspondences ??= new CorrespondenceRepository($this->runtime);
        $this->versions ??= new CorrespondenceVersionRepository($this->runtime);
        $this->parties ??= new CorrespondencePartyRepository($this->runtime);
        $this->events ??= new CorrespondenceEventRepository($this->runtime);
        $this->lookups ??= new AutomationLookupRepository($this->runtime);
        $this->documentTemplates ??= new CorrespondenceDocumentTemplateRepository($this->runtime);
        $this->coreReferences ??= new CoreReferenceOptions();
        $this->relations ??= new CorrespondenceRelationRepository($this->runtime);
        $this->enterpriseContext ??= new EnterpriseAutomationContextService();
    }

    public function createDraft(array $input, int $userId, array $context): array
    {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

        } catch (Throwable) {
            return [
                'ok' => false,

                'errors' => [
                    'organizational_context_required',
                ],
            ];
        }

        $normalized =
            $this->normalize(
                $input,
                $context,
                true,
                $actor
            );

        if ($normalized['errors'] !== []) {
            return ['ok' => false, 'errors' => $normalized['errors']];
        }

        $pdo = $this->runtime->connection();
        $now = Clock::databaseTimestamp();
        $publicReference = $this->publicReference();

        try {
            $pdo->beginTransaction();

            $correspondenceId = $this->correspondences->insert([
                'public_reference' =>
                    $publicReference,

                'root_organization_id' =>
                    $actor[
                        'root_organization_id'
                    ],

                'root_organization_public_reference' =>
                    $actor[
                        'root_organization_reference'
                    ],

                'organization_id' =>
                    $actor[
                        'organization_id'
                    ],

                'organization_public_reference' =>
                    $actor[
                        'organization_reference'
                    ],

                'org_unit_id' =>
                    $actor[
                        'org_unit_id'
                    ],

                'org_unit_public_reference' =>
                    $actor[
                        'org_unit_reference'
                    ],

                'secretariat_desk_id' =>
                    null,

                'direction_code' =>
                    $normalized[
                        'direction_code'
                    ],
                'status_code' => 'draft',
                'subject' => $normalized['subject'],
                'summary' => $normalized['summary'],
                'document_template_version_id' => $normalized['document_template_version_id'],
                'priority_code' => $normalized['priority_code'],
                'confidentiality_code' => $normalized['confidentiality_code'],
                'channel_code' => $normalized['channel_code'],
                'external_number' => $normalized['external_number'],
                'external_date' => $normalized['external_date'],
                'received_at' => $normalized['received_at'],
                'dispatched_at' => $normalized['dispatched_at'],
                'created_by_user_id' =>
                    $userId,

                'creating_appointment_reference' =>
                    $actor[
                        'appointment_reference'
                    ],

                'organizational_context_snapshot_json' =>
                    $actor[
                        'snapshot_json'
                    ],

                'updated_by_user_id' =>
                    $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $versionId = $this->versions->create($correspondenceId, 1, [
                'subject' => $normalized['subject'],
                'content' => $normalized['content'],
                'summary' => $normalized['summary'],
                'document_template_snapshot_json' => $normalized['document_template_snapshot_json'],
                'change_note' => 'ایجاد پیش نویس',
                'created_by_user_id' => $userId,
                'created_at' => $now,
            ]);

            $this->parties->insertMany($correspondenceId, $normalized['parties'], $now);
            $this->relations->replaceForDraft($correspondenceId, $normalized['relations'], $userId, $now);
            $this->events->append(
                $correspondenceId,
                'created',
                $userId,
                null,
                'draft',
                ['version' => 1],
                $now,
                $actor
            );
            $this->correspondences->updateCurrentVersion($correspondenceId, $versionId, 1, $userId, $now);

            $pdo->commit();

            return ['ok' => true, 'public_reference' => $publicReference];
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'errors' => ['runtime_unavailable']];
        }
    }

    public function updateDraft(string $publicReference, array $input, int $userId, array $context): array
    {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

        } catch (Throwable) {
            return [
                'ok' => false,

                'errors' => [
                    'organizational_context_required',
                ],
            ];
        }

        $normalized =
            $this->normalize(
                $input,
                $context,
                false,
                $actor
            );
        $expectedLock = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($expectedLock === false || $expectedLock === null) {
            $normalized['errors'][] = 'stale_update';
        }

        if ($normalized['errors'] !== []) {
            return ['ok' => false, 'errors' => $normalized['errors']];
        }

        $pdo = $this->runtime->connection();
        $now = Clock::databaseTimestamp();

        try {
            $pdo->beginTransaction();
            $current = $this->correspondences->findByPublicReferenceForUpdate($publicReference);

            if ($current === null || ($current['status_code'] ?? '') !== 'draft') {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'errors' => [
                        'not_editable',
                    ],
                ];
            }

            try {
                $this->enterpriseContext
                    ->assertCorrespondenceAccess(
                        $current,
                        $actor
                    );

            } catch (RuntimeException) {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'errors' => [
                        'forbidden_scope',
                    ],
                ];
            }

            if ((int) ($current['lock_version'] ?? -1) !== (int) $expectedLock) {
                $pdo->rollBack();
                return ['ok' => false, 'errors' => ['stale_update']];
            }

            $versionNumber = ((int) ($current['current_version_number'] ?? 0)) + 1;
            $versionId = $this->versions->create((int) $current['id'], $versionNumber, [
                'subject' => $normalized['subject'],
                'content' => $normalized['content'],
                'summary' => $normalized['summary'],
                'document_template_snapshot_json' => $normalized['document_template_snapshot_json'],
                'change_note' => trim((string) ($input['change_note'] ?? '')) ?: 'ویرایش پیش نویس',
                'created_by_user_id' => $userId,
                'created_at' => $now,
            ]);

            $updated = $this->correspondences->updateDraft((int) $current['id'], [
                'direction_code' => $normalized['direction_code'],
                'subject' => $normalized['subject'],
                'summary' => $normalized['summary'],
                'document_template_version_id' => $normalized['document_template_version_id'],
                'priority_code' => $normalized['priority_code'],
                'confidentiality_code' => $normalized['confidentiality_code'],
                'channel_code' => $normalized['channel_code'],
                'external_number' => $normalized['external_number'],
                'external_date' => $normalized['external_date'],
                'received_at' => $normalized['received_at'],
                'dispatched_at' => $normalized['dispatched_at'],
                'updated_by_user_id' => $userId,
                'updated_at' => $now,
            ], (int) $expectedLock);

            if (!$updated) {
                $pdo->rollBack();
                return ['ok' => false, 'errors' => ['stale_update']];
            }

            $this->correspondences->updateCurrentVersion((int) $current['id'], $versionId, $versionNumber, $userId, $now, false);
            $this->parties->replaceForDraft((int) $current['id'], $normalized['parties'], $now);
            $this->relations->replaceForDraft((int) $current['id'], $normalized['relations'], $userId, $now);
            $this->events->append(
                (int) $current['id'],
                'revised',
                $userId,
                'draft',
                'draft',
                [
                    'version' =>
                        $versionNumber,
                ],
                $now,
                $actor
            );

            $pdo->commit();

            return ['ok' => true, 'public_reference' => $publicReference];
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'errors' => ['runtime_unavailable']];
        }
    }

    private function normalize(
        array $input,
        array $context,
        bool $creating,
        array $actor
    ): array
    {
        $errors = [];
        $subject = $this->trim($input['subject'] ?? '', 500);
        $summary = $this->trim($input['summary'] ?? '', 2000);
        $contentFormat =
            $this->code(
                $input[
                    'content_format_code'
                ] ?? 'plain'
            );

        $contentInput =
            (string) (
                $input[
                    'content'
                ] ?? ''
            );

        $content =
            $contentFormat === 'html'
                ? (
                    new CorrespondenceRichTextContent()
                )->encodeHtml(
                    $contentInput
                )
                : $this->trim(
                    $contentInput,
                    8000
                );
        $direction = $this->code($input['direction_code'] ?? '');
        $priority = $this->code($input['priority_code'] ?? 'normal');
        $confidentiality = $this->code($input['confidentiality_code'] ?? 'normal');
        $channel =
            $direction === 'incoming'
                ? $this->code(
                    $input['channel_code']
                    ?? 'manual'
                )
                : 'manual';
        $templateReference = trim((string) ($input['document_template_reference'] ?? ''));
        $templateVersion = $templateReference !== '' ? $this->documentTemplates->activeVersion($templateReference) : null;

        if ($subject === '') {
            $errors[] = 'subject_required';
        }

        if ($direction !== 'incoming' && $content === '') {
            $errors[] = 'content_required';
        }

        if ($direction !== 'incoming' && $templateVersion === null) {
            $errors[] = 'document_template_required';
        }

        foreach ([
            'correspondence_direction' => $direction,
            'correspondence_priority' => $priority,
            'correspondence_confidentiality' => $confidentiality,
        ] as $domain => $code) {
            if (!$this->lookups->valid($domain, $code)) {
                $errors[] = 'invalid_lookup';
            }
        }

        if (
            $direction === 'incoming'
            && !$this->lookups->valid(
                'correspondence_channel',
                $channel
            )
        ) {
            $errors[] = 'invalid_lookup';
        }

        $organizationId =
            (int) (
                $actor[
                    'organization_id'
                ] ?? 0
            );

        if ($organizationId < 1) {
            $errors[] =
                'organization_required';
        }

        $externalDate =
            $direction === 'incoming'
                ? $this->dateInput(
                    $input,
                    'external_date',
                    'external_date_fa',
                    $errors
                )
                : null;

        $parties =
            $this->normalizeParties(
                $input,
                $errors,
                $actor[
                    'repository_scope'
                ]
            );

        $this->validatePartiesForDirection(
            $parties,
            $direction,
            $errors
        );

        $relations =
            $this->normalizeRelations(
                $input,
                $errors,
                $actor[
                    'repository_scope'
                ]
            );

        if ($parties === []) {
            $errors[] = 'party_required';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'organization_id' => $organizationId,
            'subject' => $subject,
            'summary' => $summary !== '' ? $summary : null,
            'document_template_version_id' => $templateVersion !== null ? (int) $templateVersion['version_id'] : null,
            'document_template_snapshot_json' => $templateVersion !== null
                ? json_encode($this->documentTemplates->snapshot($templateVersion), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'content' => $content,
            'direction_code' => $direction,
            'priority_code' => $priority,
            'confidentiality_code' => $confidentiality,
            'channel_code' => $channel,
            'external_number' =>
                $direction === 'incoming'
                    ? $this->nullable(
                        $input['external_number'] ?? '',
                        190
                    )
                    : null,
            'external_date' => $externalDate,
            'received_at' => null,
            'dispatched_at' => null,
            'parties' => $parties,
            'relations' => $relations,
        ];
    }

    private function normalizeRelations(
        array $input,
        array &$errors,
        array $scope
    ): array
    {
        $types = $this->arrayInput($input['relation_type_code'] ?? []);
        $references = $this->arrayInput($input['related_correspondence_reference'] ?? []);
        $notes = $this->arrayInput($input['relation_note'] ?? []);
        $selfReference = trim((string) ($input['form_public_reference'] ?? ''));
        $relations = [];
        $allowed = ['reply_to', 'follow_up', 'continuation', 'replacement', 'related', 'cancellation_reference'];
        $max = min(5, max(count($types), count($references)));
        for ($i = 0; $i < $max; $i++) {
            $type = $this->code($types[$i] ?? '');
            $reference = trim((string) ($references[$i] ?? ''));
            if ($type === '' && $reference === '') continue;
            $targetId =
                $reference !== ''
                    ? $this->relations
                        ->targetId(
                            $reference,
                            $scope
                        )
                    : null;
            if (!in_array($type, $allowed, true) || $targetId === null || ($selfReference !== '' && hash_equals($selfReference, $reference))) {
                $errors[] = 'invalid_relation';
                continue;
            }
            $relations[] = ['relation_type_code' => $type, 'target_correspondence_id' => $targetId, 'note' => $this->nullable($notes[$i] ?? '', 1000)];
        }
        return $relations;
    }

    private function normalizeParties(
        array $input,
        array &$errors,
        array $scope
    ): array
    {
        $roles = $this->arrayInput($input['party_role_code'] ?? []);
        $kinds = $this->arrayInput($input['party_kind'] ?? []);
        $tokens = $this->arrayInput($input['party_reference_token'] ?? []);
        $names = $this->arrayInput($input['external_display_name'] ?? []);
        $organizations = $this->arrayInput($input['external_organization_name'] ?? []);
        $contacts = $this->arrayInput($input['external_contact_or_address'] ?? []);

        /*
         * external-recipient-directory-bridge-v2
         *
         * Stable references are persisted alongside the
         * historical/display snapshot fields.
         */
        $externalOrganizationReferences =
            $this->arrayInput(
                $input[
                    'external_organization_public_reference'
                ] ?? []
            );

        $externalContactPointReferences =
            $this->arrayInput(
                $input[
                    'external_contact_point_public_reference'
                ] ?? []
            );

        $parties = [];

        $max =
            min(
                6,
                max(
                    count($roles),
                    count($kinds),
                    count($tokens),
                    count($names),
                    count($externalOrganizationReferences),
                    count($externalContactPointReferences)
                )
            );

        for ($i = 0; $i < $max; $i++) {
            $role = $this->code($roles[$i] ?? '');
            $kind = $this->code($kinds[$i] ?? '');

            /*
             * Pre-rendered recipient rows may carry role/kind
             * defaults while still being hidden and unused.
             * Only actual party data makes a row substantive.
             */
            $tokenValue =
                trim(
                    (string) (
                        $tokens[$i]
                        ?? ''
                    )
                );

            $nameValue =
                trim(
                    (string) (
                        $names[$i]
                        ?? ''
                    )
                );

            $organizationValue =
                trim(
                    (string) (
                        $organizations[$i]
                        ?? ''
                    )
                );

            $contactValue =
                trim(
                    (string) (
                        $contacts[$i]
                        ?? ''
                    )
                );

            $externalOrganizationReferenceValue =
                trim(
                    (string) (
                        $externalOrganizationReferences[$i]
                        ?? ''
                    )
                );

            $externalContactPointReferenceValue =
                trim(
                    (string) (
                        $externalContactPointReferences[$i]
                        ?? ''
                    )
                );

            if (
                $tokenValue === ''
                && $nameValue === ''
                && $organizationValue === ''
                && $contactValue === ''
                && $externalOrganizationReferenceValue === ''
                && $externalContactPointReferenceValue === ''
            ) {
                continue;
            }

            if (!$this->lookups->valid('correspondence_party_role', $role)) {
                $errors[] = 'invalid_party_role';
                continue;
            }

            if ($kind === 'external') {
                $name = $this->trim($names[$i] ?? '', 255);

                if (
                    $name === ''
                    && $externalOrganizationReferenceValue === ''
                    && $externalContactPointReferenceValue === ''
                ) {
                    $errors[] =
                        'external_party_required';

                    continue;
                }

                $externalOrganizationNameSnapshot =
                    $this->nullable(
                        $organizations[$i]
                        ?? '',
                        255
                    );

                $externalContactSnapshot =
                    $this->nullable(
                        $contacts[$i]
                        ?? '',
                        1000
                    );

                $externalOrganizationReference =
                    $this->externalDirectoryReference(
                        $externalOrganizationReferenceValue
                    );

                $externalContactPointReference =
                    $this->externalDirectoryReference(
                        $externalContactPointReferenceValue
                    );

                if (
                    (
                        $externalOrganizationReferenceValue !== ''
                        && $externalOrganizationReference === null
                    )
                    ||
                    (
                        $externalContactPointReferenceValue !== ''
                        && $externalContactPointReference === null
                    )
                ) {
                    $errors[] =
                        'invalid_external_directory_reference';

                    continue;
                }

                /*
                 * A contact point is never meaningful without
                 * its owning external organization reference.
                 */
                if (
                    $externalContactPointReference !== null
                    && $externalOrganizationReference === null
                ) {
                    $errors[] =
                        'external_directory_organization_required';

                    continue;
                }

                /*
                 * external-recipient-directory-bridge-v3a
                 *
                 * UUID shape validation from V2 is not enough.
                 * Explicit directory bindings must be resolved
                 * against Core before they enter automation DB.
                 */
                if (
                    $externalOrganizationReference !== null
                    || $externalContactPointReference !== null
                ) {
                    $directoryResolution =
                        $this
                            ->externalDirectoryResolver()
                            ->resolve(
                                $externalOrganizationReference,
                                $externalContactPointReference
                            );

                    if (
                        ($directoryResolution['ok'] ?? false)
                        !== true
                    ) {
                        $errors[] =
                            (string) (
                                $directoryResolution['error']
                                ?? 'invalid_external_directory_reference'
                            );

                        continue;
                    }

                    $externalOrganizationReference =
                        $directoryResolution[
                            'external_organization_public_reference'
                        ] ?? null;

                    $externalContactPointReference =
                        $directoryResolution[
                            'external_contact_point_public_reference'
                        ] ?? null;

                    $resolvedOrganizationName =
                        trim(
                            (string) (
                                $directoryResolution[
                                    'external_organization_name'
                                ]
                                ?? ''
                            )
                        );

                    $resolvedContactPointTitle =
                        trim(
                            (string) (
                                $directoryResolution[
                                    'external_contact_point_title'
                                ]
                                ?? ''
                            )
                        );

                    if (
                        $resolvedOrganizationName
                        !== ''
                    ) {
                        $externalOrganizationNameSnapshot =
                            $this->nullable(
                                $resolvedOrganizationName,
                                255
                            );
                    }

                    if (
                        $resolvedContactPointTitle
                        !== ''
                    ) {
                        $externalContactSnapshot =
                            $this->nullable(
                                $resolvedContactPointTitle,
                                1000
                            );
                    }

                    if ($name === '') {
                        $name =
                            $resolvedContactPointTitle
                            !== ''
                                ? $resolvedContactPointTitle
                                : $resolvedOrganizationName;
                    }
                }

                if ($name === '') {
                    $errors[] =
                        'external_party_required';

                    continue;
                }

                $parties[] = [
                    'party_role_code' => $role,
                    'target_kind_code' => 'external',
                    'person_id' => null,
                    'organization_id' => null,
                    'org_unit_id' => null,
                    'external_display_name' => $name,
                    'external_organization_name' =>
                        $externalOrganizationNameSnapshot,

                    'external_contact_or_address' =>
                        $externalContactSnapshot,

                    'external_organization_public_reference' =>
                        $externalOrganizationReference,

                    'external_contact_point_public_reference' =>
                        $externalContactPointReference,
                ];
                continue;
            }

            $reference = $this->coreReferences->decode((string) ($tokens[$i] ?? ''));

            if (
                $reference === null
                || !$this->coreReferences
                    ->referenceAllowed(
                        $reference,
                        $scope
                    )
            ) {
                $errors[] =
                    'invalid_core_reference';

                continue;
            }

            $kind =
                (string) (
                    $reference[
                        'kind'
                    ] ?? ''
                );

            $personId = null;
            $organizationId = null;
            $orgUnitId = null;
            $targetKind = $kind;

            if ($kind === CoreReferenceType::USER) {
                $personId = $this->coreReferences->userPersonId((int) $reference['id']);
                $targetKind = 'person';

                if ($personId === null) {
                    $errors[] = 'invalid_core_reference';
                    continue;
                }
            } elseif ($kind === CoreReferenceType::PERSON) {
                $personId = (int) $reference['id'];
                $targetKind = 'person';
            } elseif ($kind === CoreReferenceType::ORGANIZATION) {
                $organizationId = (int) $reference['id'];
                $targetKind = 'organization';
            } elseif ($kind === CoreReferenceType::ORG_UNIT) {
                $orgUnitId = (int) $reference['id'];
                $targetKind = 'org_unit';
            } else {
                $errors[] = 'invalid_core_reference';
                continue;
            }

            $parties[] = [
                'party_role_code' => $role,
                'target_kind_code' => $targetKind,
                'person_id' => $personId,
                'organization_id' => $organizationId,
                'org_unit_id' => $orgUnitId,
                'external_display_name' => null,
                'external_organization_name' => null,
                'external_contact_or_address' => null,

                'external_organization_public_reference' =>
                    null,

                'external_contact_point_public_reference' =>
                    null,
            ];
        }

        return $parties;
    }

    private function validatePartiesForDirection(array $parties, string $direction, array &$errors): void
    {
        if (!in_array($direction, ['incoming', 'outgoing', 'internal'], true)) {
            return;
        }

        $senders = array_values(array_filter($parties, static fn (array $party): bool => ($party['party_role_code'] ?? '') === 'sender'));
        $receivers = array_values(array_filter($parties, static fn (array $party): bool => ($party['party_role_code'] ?? '') === 'primary_recipient'));

        if ($senders === []) {
            $errors[] = 'sender_required';
        }

        if ($receivers === []) {
            $errors[] = 'receiver_required';
        }

        $isExternal = static fn (array $party): bool => ($party['target_kind_code'] ?? '') === 'external';

        if ($direction === 'incoming') {
            if (array_filter($senders, static fn (array $party): bool => !$isExternal($party)) !== []) {
                $errors[] = 'incoming_sender_must_be_external';
            }
            if (array_filter($receivers, $isExternal) !== []) {
                $errors[] = 'incoming_receiver_must_be_internal';
            }
        }

        if ($direction === 'outgoing') {
            if (
                array_filter(
                    $senders,
                    $isExternal
                ) !== []
            ) {
                $errors[] =
                    'outgoing_sender_must_be_internal';
            }

            if (
                array_filter(
                    $receivers,
                    static fn (array $party): bool =>
                        !$isExternal($party)
                ) !== []
            ) {
                $errors[] =
                    'outgoing_receiver_must_be_external';
            }

            /*
             * external-recipient-directory-outgoing-policy-v3b
             *
             * Primary outgoing recipients must be bound
             * explicitly to an external organization and
             * one correspondence destination.
             */
            foreach ($receivers as $receiver) {
                if (
                    (
                        $receiver[
                            'external_organization_public_reference'
                        ]
                        ?? null
                    ) === null
                ) {
                    $errors[] =
                        'external_directory_organization_required';

                    break;
                }
            }

            foreach ($receivers as $receiver) {
                if (
                    (
                        $receiver[
                            'external_contact_point_public_reference'
                        ]
                        ?? null
                    ) === null
                ) {
                    $errors[] =
                        'external_directory_contact_point_required';

                    break;
                }
            }
        }

        if ($direction === 'internal' && array_filter($parties, $isExternal) !== []) {
            $errors[] = 'internal_parties_must_be_internal';
        }
    }

    private function publicReference(): string
    {
        return 'COR-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function externalDirectoryResolver():
        ExternalOrganizationDirectoryReferenceResolver
    {
        return
            $this->externalDirectory ??=
                new ExternalOrganizationDirectoryReferenceResolver();
    }

    private function externalDirectoryReference(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        /*
         * V2 validates only the stable-reference shape.
         * V3 resolves ownership/existence against Core
         * before the UI is allowed to submit these values.
         */
        return
            preg_match(
                '/^[0-9a-f]{8}-'
                . '[0-9a-f]{4}-'
                . '[1-5][0-9a-f]{3}-'
                . '[89ab][0-9a-f]{3}-'
                . '[0-9a-f]{12}$/i',
                $value
            ) === 1
                ? $value
                : null;
    }

    private function arrayInput(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [$value];
    }

    private function trim(mixed $value, int $max): string
    {
        $value = trim((string) ($value ?? ''));

        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $value = $this->trim($value, $max);

        return $value === '' ? null : $value;
    }

    private function code(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^[a-z0-9_]+$/', $value) === 1 ? $value : '';
    }

    private function dateInput(array $input, string $gregorianKey, string $persianKey, array &$errors): ?string
    {
        $gregorian = trim((string) ($input[$gregorianKey] ?? ''));
        if ($gregorian !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian) === 1) {
            return $gregorian;
        }

        $persian = trim((string) ($input[$persianKey] ?? ''));
        if ($persian === '') {
            return null;
        }

        try {
            return PersianDate::toGregorianDate($persian);
        } catch (RuntimeException) {
            $errors[] = 'invalid_date';
            return null;
        }
    }
}
