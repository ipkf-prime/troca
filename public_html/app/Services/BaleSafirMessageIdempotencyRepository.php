<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * SAFIR provider-independent atomic pre-send reservation.
 * Must be called BEFORE every provider request, by every worker.
 * Does NOT call provider, approve campaigns or auto-release unknown sends.
 * Requires the reviewed shared safir_message_idempotency table.
 */
final class BaleSafirMessageIdempotencyRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param array<string,mixed> $identity From BaleSafirFinalMessageIdentity::prepare().
     * @return bool true = newly reserved, false = already reserved/sent/unknown
     */
    public function reserve(
        string $provider,
        int $campaignId,
        int $recipientId,
        string $requestId,
        array $identity
    ): bool {
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_idempotency_external_transaction_forbidden');
        }
        $phone=(string)($identity['phone_normalized'] ?? '');
        $text=(string)($identity['final_text'] ?? '');
        $messageHash=(string)($identity['message_sha256'] ?? '');
        $dedupHash=(string)($identity['dedup_sha256'] ?? '');
        $length=$identity['character_count'] ?? null;
        if ($campaignId < 1 || $recipientId < 1
            || !preg_match('/^[a-f0-9]{64}$/D', $requestId)
            || !preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $provider)
            || !preg_match('/^989[0-9]{9}$/D', $phone)
            || !is_int($length) || $length < 1
            || !mb_check_encoding($text, 'UTF-8')
            || mb_strlen($text, 'UTF-8') !== $length
            || !hash_equals(hash('sha256', $text), $messageHash)
            || !hash_equals(hash('sha256', json_encode(
                [$provider, $phone, $messageHash],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )), $dedupHash)) {
            throw new InvalidArgumentException('safir_idempotency_identity_invalid');
        }
        try {
            // Single InnoDB unique-key insert: race-safe across concurrent workers.
            // No INSERT IGNORE / REPLACE / UPSERT: existing rows must never be reset.
            $q=$this->db->prepare(
                'INSERT INTO safir_message_idempotency '
                .'(dedup_sha256,provider_code,phone_normalized,message_sha256,'
                .'character_count,campaign_id,recipient_id,request_id,state_code) '
                ."VALUES (?,?,?,?,?,?,?,?,'reserved')"
            );
            $q->execute([
                $dedupHash,$provider,$phone,$messageHash,$length,
                $campaignId,$recipientId,$requestId
            ]);
            return true;
        } catch (PDOException $e) {
            // Only duplicate-key is a safe idempotent rejection; all other
            // errors (missing table, connectivity, permissions) fail closed.
            $driverCode=(int)($e->errorInfo[1] ?? 0);
            if ($e->getCode()==='23000' && $driverCode===1062) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Outcome must be recorded after provider interaction.
     * 'unknown' is a permanent hold until externally reconciled.
     * A crash before this call leaves 'reserved', also a permanent hold.
     */
    public function recordOutcome(
        string $requestId,
        string $outcome,
        ?string $providerMessageId=null
    ): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $requestId)
            || !in_array($outcome,['accepted','unknown'],true)
            || ($providerMessageId !== null && strlen($providerMessageId)>180)) {
            throw new InvalidArgumentException('safir_idempotency_outcome_invalid');
        }
        $q=$this->db->prepare(
            'UPDATE safir_message_idempotency '
            .'SET state_code=?,provider_message_id=?,resolved_at=NOW(6) '
            ."WHERE request_id=? AND state_code='reserved'"
        );
        $q->execute([$outcome,$providerMessageId,$requestId]);
        if ($q->rowCount()!==1) {
            throw new RuntimeException('safir_idempotency_outcome_not_reservable');
        }
    }
}
