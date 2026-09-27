<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Ticketing\TicketLifecycleService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * B7-A125: Requester-only text reply from one verified private Bale binding.
 * Do not retry an ambiguous ticket write: the cross-database reservation is
 * durable BEFORE the Ticketing operation, so retries can never write twice.
 */
final class BaleTicketReplyService
{
    public static function parse(string $command): ?array
    {
        if (preg_match('~^/reply ([A-Za-z0-9_-]{3,100}) ([^\x00]{3,1200})$~usD', $command, $m) !== 1) {
            return null;
        }
        $body = trim($m[2]);
        $length = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
        if ($body === '' || $length < 3 || $length > 1000) {
            return null;
        }
        return ['reference' => $m[1], 'body' => $body];
    }

    public function submit(PDO $db, int $providerId, int $userId, array $input, string $command, array $dialog): array
    {
        $parsed = self::parse($command);
        if ($parsed === null) {
            return ['reply' => BaleDialogContentService::text($dialog, 'reply.text_01'), 'link_path' => null];
        }
        $reference = $parsed['reference'];
        $supportAccess = new BaleSupportAccessService();
        // Narrow requester visibility; staff / manager visibility is not enough.
        $detail = $supportAccess->ticketForUser($reference, $userId);
        if (!is_array($detail) || !is_array($detail['ticket'] ?? null)) {
            return ['reply' => BaleDialogContentService::text($dialog, 'reply.text_02'), 'link_path' => null];
        }

        $hash = hash('sha256', $command);
        $updateId = (string) $input['update_id'];
        $chatId = (string) $input['chat_id'];
        $senderId = (string) $input['sender_id'];
        $insert = $db->prepare(
            "INSERT INTO bale_ticket_reply_requests " .
            "(provider_instance_id, update_id, user_id, chat_id, sender_id, request_sha256, ticket_reference, status_code) " .
            "VALUES (?, ?, ?, ?, ?, ?, ?, 'reserved')"
        );
        try {
            // Unique(provider_instance_id, update_id), no automatic retry of writes.
            $insert->execute([$providerId, $updateId, $userId, $chatId, $senderId, $hash, $reference]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = $db->prepare(
                'SELECT user_id, chat_id, sender_id, request_sha256, ticket_reference, status_code ' .
                'FROM bale_ticket_reply_requests WHERE provider_instance_id = ? AND update_id = ? LIMIT 1'
            );
            $existing->execute([$providerId, $updateId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int) $row['user_id'] !== $userId ||
                (string) $row['chat_id'] !== $chatId || (string) $row['sender_id'] !== $senderId ||
                !hash_equals((string) $row['request_sha256'], $hash) ||
                (string) $row['ticket_reference'] !== $reference) {
                throw new RuntimeException('REPLY_UPDATE_ID_COLLISION');
            }
            $status = (string) ($row['status_code'] ?? '');
            $reply = $status === 'sent'
                ? BaleDialogContentService::text($dialog, 'reply.text_03')
                : ($status === 'rejected'
                    ? BaleDialogContentService::text($dialog, 'reply.text_04')
                    : BaleDialogContentService::text($dialog, 'reply.text_05'));
            return ['reply' => $reply, 'link_path' => '/admin/ticketing/tickets/' . rawurlencode($reference)];
        }

        try {
            // Lifecycle service independently checks requester, assignment and status.
            $result = (new TicketLifecycleService())->requesterReply(
                $reference,
                $parsed['body'],
                $userId,
                []
            );
            $ok = !empty($result['ok']);
            $status = $ok ? 'sent' : 'rejected';
        } catch (Throwable) {
            // The write could have succeeded before the exception. Never retry it.
            $this->finish($db, $providerId, $updateId, 'unknown');
            return [
                'reply' => BaleDialogContentService::text($dialog, 'reply.text_06'),
                'link_path' => '/admin/ticketing/tickets/' . rawurlencode($reference),
            ];
        }

        $this->finish($db, $providerId, $updateId, $status);
        if ($ok) {
            return [
                'reply' => BaleDialogContentService::text($dialog, 'reply.text_07'),
                'link_path' => '/admin/ticketing/tickets/' . rawurlencode($reference),
            ];
        }
        $reason = (string) ($result['status'] ?? '');
        $message = match ($reason) {
            'requester_update_forbidden_state' => BaleDialogContentService::text($dialog, 'reply.text_08'),
            'requester_reply_forbidden' => BaleDialogContentService::text($dialog, 'reply.text_09'),
            'requester_reply_empty', 'requester_reply_too_long' => BaleDialogContentService::text($dialog, 'reply.text_10'),
            default => BaleDialogContentService::text($dialog, 'reply.text_11'),
        };
        return ['reply' => $message, 'link_path' => '/admin/ticketing/tickets/' . rawurlencode($reference)];
    }


    /**
     * Click-only, requester-scoped resolution. Independent canonical Ticketing
     * authorization; reservation precedes mutation, as with text replies.
     */
    public function resolve(PDO $db, int $providerId, int $userId, array $input, string $reference, array $dialog): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{3,100}$/D', $reference)!==1) {
            return ['reply'=>BaleDialogContentService::text($dialog, 'reply.text_12'),'link_path'=>null];
        }
        $path='/admin/ticketing/tickets/'.rawurlencode($reference);
        $supportAccess=new BaleSupportAccessService();
        $detail=$supportAccess->ticketForUser($reference,$userId);
        if (!is_array($detail) || !is_array($detail['ticket']??null)) {
            return ['reply'=>BaleDialogContentService::text($dialog, 'reply.text_13'),'link_path'=>null];
        }
        $providerId=(int)$providerId;
        $updateId=(string)$input['update_id'];
        $chatId=(string)$input['chat_id'];
        $senderId=(string)$input['sender_id'];
        $hash=hash('sha256','resolve|'.$reference);
        $reserve=$db->prepare("INSERT INTO bale_ticket_reply_requests " .
            "(provider_instance_id, update_id, user_id, chat_id, sender_id, request_sha256, ticket_reference, status_code) " .
            "VALUES (?, ?, ?, ?, ?, ?, ?, 'reserved')");
        try {
            $reserve->execute([$providerId,$updateId,$userId,$chatId,$senderId,$hash,$reference]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode()!=='23000') throw $exception;
            $check=$db->prepare('SELECT user_id, chat_id, sender_id, request_sha256, ticket_reference, status_code FROM bale_ticket_reply_requests WHERE provider_instance_id=? AND update_id=? LIMIT 1');
            $check->execute([$providerId,$updateId]);
            $previous=$check->fetch(PDO::FETCH_ASSOC);
            if (!is_array($previous) || (int)$previous['user_id']!==$userId ||
                (string)$previous['chat_id']!==$chatId || (string)$previous['sender_id']!==$senderId ||
                !hash_equals((string)$previous['request_sha256'],$hash) ||
                (string)$previous['ticket_reference']!==$reference) throw new RuntimeException('RESOLVE_UPDATE_COLLISION');
            return ['reply'=>BaleDialogContentService::text($dialog, 'reply.text_14'), 'link_path'=>$path];
        }
        try {
            $result=(new TicketLifecycleService())->requesterResolve($reference,$userId);
            $ok=!empty($result['ok']);
            $this->finish($db,$providerId,$updateId,$ok?'sent':'rejected');
            if ($ok) return ['reply'=>BaleDialogContentService::text($dialog, 'reply.text_15'),'link_path'=>$path];
            $status=(string)($result['status']??'');
            $message=match ($status) {
                'requester_resolve_forbidden_state'=>BaleDialogContentService::text($dialog, 'reply.text_16'),
                'requester_reply_forbidden'=>BaleDialogContentService::text($dialog, 'reply.text_17'),
                default=>BaleDialogContentService::text($dialog, 'reply.text_18'),
            };
            return ['reply'=>$message,'link_path'=>$path];
        } catch (Throwable) {
            $this->finish($db,$providerId,$updateId,'unknown');
            return ['reply'=>BaleDialogContentService::text($dialog, 'reply.text_19'),'link_path'=>$path];
        }
    }

    private function finish(PDO $db, int $providerId, string $updateId, string $status): void
    {
        $change = $db->prepare(
            "UPDATE bale_ticket_reply_requests SET status_code = ? " .
            "WHERE provider_instance_id = ? AND update_id = ? AND status_code = 'reserved'"
        );
        $change->execute([$status, $providerId, $updateId]);
    }
}
