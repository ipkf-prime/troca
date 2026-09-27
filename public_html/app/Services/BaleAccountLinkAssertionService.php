<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * IPKF-side signing protocol for NP Bale Bot account linking.
 *
 * INTERNAL SERVICE ONLY. This class does not validate an HTTP
 * session and must not be exposed directly to a request handler.
 *
 * A future website adapter must:
 *  - validate the current IPKF user and completed authentication;
 *  - obtain the subject ID and authentication time server-side;
 *  - require explicit user confirmation and a valid CSRF token;
 *  - select connector identity and secret from trusted configuration;
 *  - never accept an external subject or signing key from the browser.
 *
 * The signed assertion does not grant Ticketing permissions.
 */
final class BaleAccountLinkAssertionService
{
    /**
     * Produce the wire format verified by the bot's
     * npBaleLinkAssertionVerify() protocol version 1.
     *
     * @return array{claims: array<string, int|string>, signature: string}
     */
    public function signVerifiedContext(
        string $connectorCode,
        string $linkToken,
        string $externalSubjectId,
        int $sessionAuthenticatedAt,
        string $trustedConnectorSecret
    ): array {
        $now = time();

        if (
            preg_match(
                '/^[A-Za-z][A-Za-z0-9_-]{0,47}$/D',
                $connectorCode
            ) !== 1 ||
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $linkToken
            ) !== 1 ||
            $externalSubjectId === '' ||
            strlen($externalSubjectId) > 191 ||
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $externalSubjectId
            ) === 1 ||
            strlen($trustedConnectorSecret) < 32 ||
            $sessionAuthenticatedAt <= 0 ||
            $sessionAuthenticatedAt > $now ||
            $sessionAuthenticatedAt < $now - 300
        ) {
            throw new RuntimeException(
                'BALE_LINK_SIGNING_INPUT_INVALID'
            );
        }

        $claims = [
            'version' => 1,
            'connector_code' => $connectorCode,
            'link_token' => $linkToken,
            'external_subject_id' => $externalSubjectId,
            'session_authenticated_at' => $sessionAuthenticatedAt,
            'issued_at' => $now,
            'expires_at' => $now + 60,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        /*
         * Canonical field order and domain separator MUST match
         * BaleAccountLinkAssertion.php on the destination bot.
         */
        $canonical = "np-bale-link-confirm-v1\n" .
            json_encode(
                array_values($claims),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            );

        return [
            'claims' => $claims,
            'signature' => hash_hmac(
                'sha256',
                $canonical,
                $trustedConnectorSecret
            ),
        ];
    }
}
