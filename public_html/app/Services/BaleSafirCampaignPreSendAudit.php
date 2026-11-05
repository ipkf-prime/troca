<?php
declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Read-only preview for the existing UI-imported SAFIR session campaign.
 * Does NOT approve, enqueue or send. Historical pre-ledger sends still
 * require reconciliation before any live bulk run.
 */
final class BaleSafirCampaignPreSendAudit
{
    public function __construct(private readonly PDO $db) {}

    public function review(array $campaign, array $template, int $maxCharacters): array
    {
        if ($maxCharacters < 1 || $maxCharacters > 100000
            || !is_string($template['body'] ?? null)
            || !is_array($template['mappings'] ?? null)
            || !($template['active'] ?? false)
            || !($template['provider_approved'] ?? false)
            || !is_array($campaign['rows'] ?? null)
            || !preg_match('/^[a-f0-9]{24}$/D', (string)($campaign['reference'] ?? ''))) {
            throw new InvalidArgumentException('safir_audit_invalid_campaign');
        }
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_audit_outer_transaction');
        }
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare(
                'SELECT state_code FROM safir_message_idempotency WHERE dedup_sha256=? LIMIT 1'
            );
            $summary=[
                'total_rows'=>count($campaign['rows']), 'skipped_import'=>0,
                'invalid_message'=>0, 'too_long'=>0, 'duplicate_in_campaign'=>0,
                'already_reserved_or_sent'=>0, 'ready'=>0,
                'status'=>'BLOCKED_PENDING_HISTORICAL_RECONCILIATION',
            ];
            $seen=[];
            foreach ($campaign['rows'] as $row) {
                if (!is_array($row) || ($row['status'] ?? '') !== 'ready') {
                    $summary['skipped_import']++;
                    continue;
                }
                try {
                    $fields=$row['fields'] ?? null;
                    if (!is_array($fields)
                        || array_keys($fields)!==array_keys($template['mappings'])) {
                        throw new InvalidArgumentException('safir_audit_fields_mismatch');
                    }
                    // Final text is used ONLY to count characters and fingerprint
                    // the provider-approved template; API payload still uses
                    // template_id + text_fields as in the successful pilot.
                    $identity=BaleSafirFinalMessageIdentity::prepare(
                        'bale',(string)($row['phone'] ?? ''),
                        $template['body'],$fields,$maxCharacters
                    );
                } catch (RuntimeException $e) {
                    if ($e->getMessage()==='safir_final_message_too_long') {
                        $summary['too_long']++;
                    } else {
                        $summary['invalid_message']++;
                    }
                    continue;
                } catch (InvalidArgumentException $e) {
                    $summary['invalid_message']++;
                    continue;
                }
                if (isset($seen[$identity['dedup_sha256']])) {
                    $summary['duplicate_in_campaign']++;
                    continue;
                }
                $seen[$identity['dedup_sha256']]=true;
                $q->execute([$identity['dedup_sha256']]);
                if ($q->fetchColumn() !== false) {
                    $summary['already_reserved_or_sent']++;
                    continue;
                }
                $summary['ready']++;
            }
            $this->db->commit();
            return $summary;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
