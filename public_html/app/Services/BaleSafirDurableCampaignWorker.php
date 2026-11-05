<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * SAFIR durable queued campaign executor.
 *
 * No routes/cron wired. Invocation requires prior canonical approval and a
 * shared, migrated database. Never call for a legacy session campaign.
 * An acknowledged provider receipt must match the existing Bale Safir API:
 * template_id / text_fields. Never replace this with a free-text payload.
 */
final class BaleSafirDurableCampaignWorker
{
    public function __construct(
        private readonly PDO $db,
        private readonly BaleSafirGuardedDispatch $guard
    ) {}

    /**
     * Runs AT MOST one row, so a supervisor may enforce rate limits/stop.
     *
     * @param callable $sendApprovedTemplate callable(string $phone,array $payload,string $requestId):array
     *                        Must return ['accepted'=>bool,'message_id'=>?string].
     * @param callable $checkSendingAllowed callable(int $campaignId,int $recipientId):bool
     *                        Recheck policy, suppression, consent, operator approval, provider rate limit.
     * @return array{state:string,recipient_id?:int}
     */
    public function step(
        int $campaignId,
        int $maxCharacters,
        callable $sendApprovedTemplate,
        callable $checkSendingAllowed
    ): array {
        if ($campaignId < 1 || $maxCharacters < 1 || $this->db->inTransaction()) {
            throw new RuntimeException('safir_worker_invalid_request');
        }
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare(
                'SELECT id,template_snapshot_json,template_sha256,approved_by_user_id,approved_at,state_code '
                .'FROM safir_campaigns WHERE id=? FOR UPDATE'
            );
            $q->execute([$campaignId]);
            $campaign=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($campaign)
                || !in_array($campaign['state_code'],['queued','running'],true)
                || empty($campaign['approved_by_user_id'])
                || empty($campaign['approved_at'])) {
                throw new RuntimeException('safir_worker_campaign_not_approved');
            }
            $template=json_decode((string)$campaign['template_snapshot_json'],true,512,JSON_THROW_ON_ERROR);
            if (!is_array($template)
                || !hash_equals((string)$campaign['template_sha256'], hash('sha256', (string)$campaign['template_snapshot_json']))
                || ($template['active'] ?? null)!==true
                || ($template['provider_approved'] ?? null)!==true) {
                throw new RuntimeException('safir_worker_template_invalid');
            }
            $q=$this->db->prepare(
                "SELECT id,phone_normalized,text_fields_json FROM safir_campaign_recipients "
                ."WHERE campaign_id=? AND state_code='ready' ORDER BY id LIMIT 1 FOR UPDATE"
            );
            $q->execute([$campaignId]);
            $recipient=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($recipient)) {
                $this->db->commit();
                return ['state'=>'empty'];
            }
            $recipientId=(int)$recipient['id'];
            $fields=json_decode((string)$recipient['text_fields_json'],true,512,JSON_THROW_ON_ERROR);
            if (!is_array($fields)) {
                throw new RuntimeException('safir_worker_fields_invalid');
            }
            // Durable audit trail and historical reconciliation must be
            // independently guaranteed by the caller's canonical policy gate.
            // The caller MUST NOT return true based solely on new-ledger absence.
            if (!$checkSendingAllowed($campaignId,$recipientId)) {
                throw new RuntimeException('safir_worker_policy_denied');
            }
            $identity=BaleSafirFinalMessageIdentity::prepare(
                'bale',(string)$recipient['phone_normalized'],
                (string)($template['body'] ?? ''),$fields,$maxCharacters
            );
            if (array_keys($fields)!==array_keys($template['mappings'] ?? [])) {
                throw new RuntimeException('safir_worker_mapping_mismatch');
            }
            if (!preg_match('/^[1-9][0-9]{5,19}$/D', (string)($template['bot_id'] ?? ''))
                || !preg_match('/^[A-Za-z0-9_-]{2,64}$/D', (string)($template['template_id'] ?? ''))) {
                throw new RuntimeException('safir_worker_provider_template_invalid');
            }
            $requestId=bin2hex(random_bytes(32));
            // Mark the row unknown and persist BEFORE any network call.
            // Crash/timeout remains unknown, never eligible for automatic retry.
            $update=$this->db->prepare(
                "UPDATE safir_campaign_recipients SET state_code='unknown', "
                ."request_id=?,attempt_count=attempt_count+1,attempted_at=NOW(6) "
                ."WHERE campaign_id=? AND id=? AND state_code='ready'"
            );
            $update->execute([$requestId,$campaignId,$recipientId]);
            if ($update->rowCount()!==1) {
                throw new RuntimeException('safir_worker_claim_race');
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        // Existing Bale provider request format, no hardcoded template variables.
        $payload=[
            'request_id'=>$requestId,
            'bot_id'=>(int)$template['bot_id'],
            'phone_number'=>$identity['phone_normalized'],
            'message_data'=>['template_message'=>[
                'template_id'=>$template['template_id'],
                'text_fields'=>(object)$fields,
            ]],
        ];
        $outcome=$this->guard->dispatch(
            'bale',$campaignId,$recipientId,$requestId,$identity,
            static fn(string $phone,string $text,string $id):array
                => $sendApprovedTemplate($phone,$payload,$id)
        );
        if ($outcome['state']==='accepted') {
            $q=$this->db->prepare(
                "UPDATE safir_campaign_recipients SET state_code='sent',"
                ."sent_at=NOW(6),provider_message_id=? "
                ."WHERE campaign_id=? AND id=? AND state_code='unknown' AND request_id=?"
            );
            $q->execute([$outcome['provider_message_id'],$campaignId,$recipientId,$requestId]);
            if ($q->rowCount()!==1) {
                throw new RuntimeException('safir_worker_sent_receipt_not_persisted');
            }
            return ['state'=>'sent','recipient_id'=>$recipientId];
        }
        // duplicate_blocked / unknown_hold remain unknown in recipient row.
        return ['state'=>$outcome['state'],'recipient_id'=>$recipientId];
    }
}
