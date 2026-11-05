<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * SAFIR: pure message identity, before any provider call.
 * No database / provider access, no permission to queue or send.
 */
final class BaleSafirFinalMessageIdentity
{
    /**
     * @param array<string, string> $fields Canonical text_fields_json decoded.
     * @return array{phone_normalized:string,final_text:string,character_count:int,message_sha256:string,dedup_sha256:string}
     */
    public static function prepare(
        string $provider,
        string $phone,
        string $templateBody,
        array $fields,
        int $maxCharacters
    ): array {
        if (!function_exists('mb_strlen') || !mb_check_encoding($templateBody, 'UTF-8')
            || $maxCharacters < 1 || $maxCharacters > 100000
            || !preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $provider)) {
            throw new InvalidArgumentException('safir_message_configuration_invalid');
        }
        $normalized = BaleSafirDurableCampaignPolicy::normalizePhone($phone);
        if ($normalized === null) {
            throw new InvalidArgumentException('safir_message_phone_invalid');
        }
        $text = preg_replace_callback(
            '/\{\{([\p{L}_][\p{L}\p{N}_]*)\}\}/u',
            static function (array $m) use ($fields): string {
                $key = $m[1];
                if (!array_key_exists($key, $fields) || !is_string($fields[$key])
                    || !mb_check_encoding($fields[$key], 'UTF-8') || trim($fields[$key]) === '') {
                    throw new InvalidArgumentException('safir_message_variable_missing');
                }
                return $fields[$key];
            },
            $templateBody
        );
        if (!is_string($text) || preg_match('/\{\{|\}\}/u', $text)
            || trim($text) === '') {
            throw new InvalidArgumentException('safir_message_unresolved_placeholder');
        }
        $length = mb_strlen($text, 'UTF-8');
        if ($length > $maxCharacters) {
            throw new RuntimeException('safir_final_message_too_long');
        }
        $messageHash = hash('sha256', $text);
        // Unambiguous canonical encoding; do not use character count alone for dedup.
        $dedupHash = hash('sha256', json_encode(
            [$provider, $normalized, $messageHash],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
        return [
            'phone_normalized' => $normalized,
            'final_text' => $text,
            'character_count' => $length,
            'message_sha256' => $messageHash,
            'dedup_sha256' => $dedupHash,
        ];
    }
}
