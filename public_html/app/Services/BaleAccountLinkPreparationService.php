<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Security\Csrf;

/**
 * Internal IPKF account-link preparation service.
 *
 * No HTTP route or network delivery is provided here.
 *
 * The future website route MUST:
 * - accept the link token only from the explicit confirmation POST;
 * - derive consent from an explicit confirmation field;
 * - obtain the connector secret exclusively from protected
 *   server-side configuration;
 * - never accept connector code, secret or external subject ID
 *   from browser input;
 * - deliver the result only to a trusted server-to-server adapter,
 *   never render the signed assertion into a browser response.
 *
 * This service does not grant Ticketing permissions.
 */
final class BaleAccountLinkPreparationService
{
    /**
     * @return array{
     *   claims:array<string,int|string>,
     *   signature:string
     * }|null
     */
    public function prepareForCurrentSession(
        string $linkToken,
        string $csrfToken,
        bool $explicitConsent,
        string $trustedConnectorCode,
        string $trustedConnectorSecret
    ): ?array {
        /*
         * IPKF is the protocol identity of this issuing site.
         * It is not an API address or a deployment-specific value.
         */
        if (
            !$explicitConsent ||
            $trustedConnectorCode !== 'IPKF' ||
            preg_match('/^[a-f0-9]{64}$/D', $linkToken) !== 1 ||
            $csrfToken === '' ||
            strlen($csrfToken) > 4096 ||
            strlen($trustedConnectorSecret) < 32
        ) {
            return null;
        }

        /*
         * CSRF must be verified against the actual IPKF session.
         * Merely presenting a valid link token is insufficient.
         */
        if (!(new Csrf())->check($csrfToken)) {
            return null;
        }

        /*
         * Identity and authentication time come only from the
         * server-side AuthService/Session adapter.
         */
        $context = (
            new BaleAccountLinkSessionService()
        )->currentVerifiedContext();

        if ($context === null) {
            return null;
        }

        return (
            new BaleAccountLinkAssertionService()
        )->signVerifiedContext(
            $trustedConnectorCode,
            $linkToken,
            $context['external_subject_id'],
            $context['session_authenticated_at'],
            $trustedConnectorSecret
        );
    }
}
