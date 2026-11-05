<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * SAFIR fail-closed dispatch boundary. Not registered to a live worker.
 * Caller is responsible for canonical campaign approval, current suppression,
 * policy/consent, pacing, final template rendering and provider configuration.
 *
 * Provider callback must return an array with ['accepted'=>true,'message_id'=>string|null]
 * ONLY when its actual provider contract confirms acceptance; all other
 * responses are treated as unknown and held for manual reconciliation.
 */
final class BaleSafirGuardedDispatch
{
    public function __construct(
        private readonly BaleSafirMessageIdempotencyRepository $idempotency
    ) {}

    /**
     * @param array<string,mixed> $identity prepared by BaleSafirFinalMessageIdentity
     * @param callable $providerCall callable(string $phone,string $text,string $requestId):array
     * @return array{state:string,provider_message_id:?string}
     */
    public function dispatch(
        string $provider,
        int $campaignId,
        int $recipientId,
        string $requestId,
        array $identity,
        callable $providerCall
    ): array {
        if (!$this->idempotency->reserve(
            $provider,$campaignId,$recipientId,$requestId,$identity
        )) {
            return ['state'=>'duplicate_blocked','provider_message_id'=>null];
        }
        // A committed reservation exists before external effects.
        try {
            $answer=$providerCall(
                (string)$identity['phone_normalized'],
                (string)$identity['final_text'],
                $requestId
            );
            if (!is_array($answer)
                || ($answer['accepted'] ?? null) !== true) {
                $this->idempotency->recordOutcome($requestId,'unknown');
                return ['state'=>'unknown_hold','provider_message_id'=>null];
            }
            $messageId=$answer['message_id'] ?? null;
            if ($messageId !== null && (!is_string($messageId)
                || $messageId==='' || strlen($messageId)>180)) {
                throw new RuntimeException('safir_provider_receipt_invalid');
            }
            $this->idempotency->recordOutcome($requestId,'accepted',$messageId);
            return ['state'=>'accepted','provider_message_id'=>$messageId];
        } catch (Throwable $e) {
            // If provider was reached, its result might have been accepted.
            // Never delete/release a reservation after an exception.
            // A failed outcome update also leaves reserved = blocked.
            try {
                $this->idempotency->recordOutcome($requestId,'unknown');
            } catch (Throwable) {
                // Existing reserved/accepted/unknown row still prevents replay.
            }
            throw $e;
        }
    }
}
