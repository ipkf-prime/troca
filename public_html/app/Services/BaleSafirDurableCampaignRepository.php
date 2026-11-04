<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * SAFIR R72B: DB-injected transactional campaign/import persistence.
 * NOT registered in routes, cron, worker or production. No HTTP/provider code.
 * Works only after the separately approved five-table SAFIR migration.
 * Caller must authorize actor, provide a reviewed PDO connection and require
 * approval and per-recipient suppression again before any future send.
 */
final class BaleSafirDurableCampaignRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{reference:string,state:string} */
    public function createDraft(
        int $actorId,
        string $title,
        array $template,
        string $purposeCode,
        string $consentBasis
    ): array {
        $title = trim($title);
        $purposeCode = trim($purposeCode);
        $consentBasis = trim($consentBasis);
        if ($actorId < 1 || $title === '' || mb_strlen($title, 'UTF-8') > 190
            || !preg_match('/^[a-z][a-z0-9_]{2,39}$/D', $purposeCode)
            || $consentBasis === '' || mb_strlen($consentBasis, 'UTF-8') > 255) {
            throw new InvalidArgumentException('safir_durable_draft_invalid');
        }
        $frozen = BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($template);
        $reference = bin2hex(random_bytes(16));
        $this->transaction(function () use ($actorId, $title, $frozen, $purposeCode, $consentBasis, $reference): void {
            $stmt = $this->db->prepare(
                'INSERT INTO safir_campaigns '
                . '(public_reference, owner_user_id, title, template_code, template_sha256, '
                . 'template_snapshot_json, state_code, purpose_code, consent_basis) '
                . "VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)"
            );
            $stmt->execute([
                $reference, $actorId, $title, $frozen['code'], $frozen['sha256'],
                $frozen['snapshot_json'], $purposeCode, $consentBasis,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('safir_durable_create_not_confirmed');
            }
        });
        return ['reference' => $reference, 'state' => 'draft'];
    }

    /**
     * Imports one bounded chunk with a stable *chunk* SHA256 (not the whole
     * campaign hash). All accepted rows, batch receipt and final state commit
     * atomically. Duplicate phones, suppressed phones and invalid source rows
     * are counted but never persisted as sendable recipients.
     *
     * @param array<int,array{row:int,columns:array}> $rows
     * @return array{source_rows:int,accepted_rows:int,rejected_rows:int,reasons:array<string,int>}
     */
    public function importChunk(
        int $actorId,
        string $campaignReference,
        string $chunkSha256,
        string $sourceFilename,
        array $rows
    ): array {
        if ($actorId < 1
            || !preg_match('/^[a-f0-9]{32}$/D', $campaignReference)
            || !preg_match('/^[a-f0-9]{64}$/D', $chunkSha256)
            || $rows === [] || count($rows) > BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
            || mb_strlen($sourceFilename, 'UTF-8') > 255) {
            throw new InvalidArgumentException('safir_durable_import_invalid');
        }
        $rowNumbers = [];
        foreach ($rows as $row) {
            $number = $row['row'] ?? null;
            if (!is_int($number) || $number < 1 || $number > 4294967295 || isset($rowNumbers[$number])) {
                throw new InvalidArgumentException('safir_durable_source_row_invalid_or_repeated');
            }
            $rowNumbers[$number] = true;
        }
        return $this->transaction(function () use (
            $actorId, $campaignReference, $chunkSha256, $sourceFilename, $rows
        ): array {
            // The campaign row lock serializes all imports for this campaign.
            $stmt = $this->db->prepare(
                'SELECT id, owner_user_id, state_code, template_code, '
                . 'template_sha256, template_snapshot_json '
                . 'FROM safir_campaigns WHERE public_reference = ? FOR UPDATE'
            );
            $stmt->execute([$campaignReference]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($campaign) || (int) $campaign['owner_user_id'] !== $actorId) {
                throw new RuntimeException('safir_durable_campaign_not_accessible');
            }
            if ((string) $campaign['state_code'] !== 'draft') {
                throw new RuntimeException('safir_durable_import_requires_draft');
            }
            $snapshotJson = (string) $campaign['template_snapshot_json'];
            if (!hash_equals((string) $campaign['template_sha256'], hash('sha256', $snapshotJson))) {
                throw new RuntimeException('safir_durable_template_snapshot_changed');
            }
            $snapshot = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot)) {
                throw new RuntimeException('safir_durable_template_invalid');
            }
            $frozen = BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($snapshot);
            if (!hash_equals((string) $campaign['template_sha256'], $frozen['sha256'])
                || !hash_equals((string) $campaign['template_code'], $frozen['code'])) {
                throw new RuntimeException('safir_durable_template_snapshot_invalid');
            }
            $campaignId = (int) $campaign['id'];
            if ($campaignId < 1) {
                throw new RuntimeException('safir_durable_campaign_id_invalid');
            }

            $checkBatch = $this->db->prepare(
                'SELECT id FROM safir_campaign_import_batches '
                . 'WHERE campaign_id = ? AND source_sha256 = ? LIMIT 1'
            );
            $checkBatch->execute([$campaignId, $chunkSha256]);
            if ($checkBatch->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException('safir_durable_chunk_already_imported');
            }

            $plan = BaleSafirDurableCampaignPolicy::planChunk($frozen, $rows);
            $batch = $this->db->prepare(
                'INSERT INTO safir_campaign_import_batches '
                . '(campaign_id, source_sha256, source_filename, state_code) '
                . "VALUES (?, ?, ?, 'processing')"
            );
            $batch->execute([$campaignId, $chunkSha256, $sourceFilename]);
            if ($batch->rowCount() !== 1 || !ctype_digit($this->db->lastInsertId())) {
                throw new RuntimeException('safir_durable_batch_create_failed');
            }
            $batchId = (int) $this->db->lastInsertId();
            if ($batchId < 1) {
                throw new RuntimeException('safir_durable_batch_id_invalid');
            }

            $findSuppressed = $this->db->prepare(
                'SELECT 1 FROM safir_suppressed_phones WHERE phone_normalized = ? LIMIT 1'
            );
            $findDuplicate = $this->db->prepare(
                'SELECT 1 FROM safir_campaign_recipients '
                . 'WHERE campaign_id = ? AND phone_normalized = ? LIMIT 1'
            );
            $insertRecipient = $this->db->prepare(
                'INSERT INTO safir_campaign_recipients '
                . '(campaign_id, import_batch_id, source_row_number, phone_normalized, '
                . "text_fields_json, state_code) VALUES (?, ?, ?, ?, ?, 'ready')"
            );
            $reasons = ['invalid_phone' => 0, 'invalid_fields' => 0,
                        'duplicate_phone' => 0, 'suppressed_phone' => 0];
            foreach ($plan['skipped'] as $skipped) {
                $reason = (string) $skipped['reason'];
                if (!array_key_exists($reason, $reasons)) {
                    throw new RuntimeException('safir_durable_unrecognized_skip_reason');
                }
                $reasons[$reason]++;
            }
            $seen = [];
            $accepted = 0;
            foreach ($plan['ready'] as $item) {
                $phone = $item['phone_normalized'];
                if (isset($seen[$phone])) {
                    $reasons['duplicate_phone']++;
                    continue;
                }
                $seen[$phone] = true;
                $findSuppressed->execute([$phone]);
                if ($findSuppressed->fetchColumn() !== false) {
                    $reasons['suppressed_phone']++;
                    continue;
                }
                $findDuplicate->execute([$campaignId, $phone]);
                if ($findDuplicate->fetchColumn() !== false) {
                    $reasons['duplicate_phone']++;
                    continue;
                }
                $insertRecipient->execute([
                    $campaignId, $batchId, $item['source_row_number'],
                    $phone, $item['text_fields_json'],
                ]);
                if ($insertRecipient->rowCount() !== 1) {
                    throw new RuntimeException('safir_durable_recipient_insert_failed');
                }
                $accepted++;
            }
            $sourceRows = count($rows);
            $rejected = $sourceRows - $accepted;
            if (array_sum($reasons) !== $rejected) {
                throw new RuntimeException('safir_durable_import_accounting_mismatch');
            }
            $finish = $this->db->prepare(
                'UPDATE safir_campaign_import_batches SET state_code = ?, '
                . 'source_rows = ?, accepted_rows = ?, rejected_rows = ?, finished_at = NOW(6) '
                . 'WHERE id = ? AND campaign_id = ? AND state_code = ?'
            );
            $finish->execute(['complete', $sourceRows, $accepted, $rejected,
                              $batchId, $campaignId, 'processing']);
            if ($finish->rowCount() !== 1) {
                throw new RuntimeException('safir_durable_batch_finalize_failed');
            }
            return ['source_rows' => $sourceRows,
                    'accepted_rows' => $accepted,
                    'rejected_rows' => $rejected,
                    'reasons' => $reasons];
        });
    }

    /** @return array{source_rows:int,accepted_rows:int,rejected_rows:int,ready_rows:int} */
    public function summaryForOwner(int $actorId, string $campaignReference): array
    {
        if ($actorId < 1 || !preg_match('/^[a-f0-9]{32}$/D', $campaignReference)) {
            throw new InvalidArgumentException('safir_durable_campaign_reference_invalid');
        }
        $owner = $this->db->prepare(
            'SELECT id FROM safir_campaigns WHERE owner_user_id = ? AND public_reference = ?'
        );
        $owner->execute([$actorId, $campaignReference]);
        $campaignId = $owner->fetchColumn();
        if ($campaignId === false) {
            throw new RuntimeException('safir_durable_campaign_not_accessible');
        }
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(source_rows),0) AS source_rows, '
            . 'COALESCE(SUM(accepted_rows),0) AS accepted_rows, '
            . 'COALESCE(SUM(rejected_rows),0) AS rejected_rows '
            . 'FROM safir_campaign_import_batches '
            . "WHERE campaign_id = ? AND state_code = 'complete'"
        );
        $statement->execute([(int) $campaignId]);
        $totals = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($totals)) {
            throw new RuntimeException('safir_durable_batch_summary_unavailable');
        }
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM safir_campaign_recipients '
            . "WHERE campaign_id = ? AND state_code = 'ready'"
        );
        $statement->execute([(int) $campaignId]);
        return [
            'source_rows' => (int) $totals['source_rows'],
            'accepted_rows' => (int) $totals['accepted_rows'],
            'rejected_rows' => (int) $totals['rejected_rows'],
            'ready_rows' => (int) $statement->fetchColumn(),
        ];
    }

    private function transaction(callable $operation): mixed
    {
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_durable_unexpected_outer_transaction');
        }
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('safir_durable_transaction_unavailable');
        }
        try {
            $result = $operation();
            if (!$this->db->commit()) {
                throw new RuntimeException('safir_durable_commit_failed');
            }
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
