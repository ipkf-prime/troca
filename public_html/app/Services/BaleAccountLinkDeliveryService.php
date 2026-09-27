<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Coordinates account-link preparation and trusted delivery.
 *
 * INTERNAL SERVICE ONLY: no HTTP route or HTTP client is
 * registered by this class.
 *
 * The eventual route must construct $trustedSender exclusively
 * from protected server-side configuration. It must never return
 * the signed envelope to the browser or accept a destination URL,
 * signing key, connector code or external subject from browser input.
 *
 * An accepted delivery confirms only that the destination reported
 * processing the assertion. Feature permissions remain the
 * responsibility of the referenced application.
 */
final class BaleAccountLinkDeliveryService
{
    /**
     * $trustedSender receives the signed envelope and must return
     * true ONLY after receiving an authenticated, positive response
     * from the configured bot endpoint.
     *
     * No automatic retries: the same assertion must not be blindly
     * resent after an uncertain transport outcome.
     */
    public function deliverForCurrentSession(
        string $linkToken,
        string $csrfToken,
        bool $explicitConsent,
        string $trustedConnectorCode,
        string $trustedConnectorSecret,
        callable $trustedSender
    ): bool {
        $prepared = (
            new BaleAccountLinkPreparationService()
        )->prepareForCurrentSession(
            $linkToken,
            $csrfToken,
            $explicitConsent,
            $trustedConnectorCode,
            $trustedConnectorSecret
        );

        if ($prepared === null) {
            return false;
        }

        /*
         * Only the trusted server-side sender receives this
         * sensitive envelope. The caller receives a boolean.
         */
        try {
            return $trustedSender($prepared) === true;
        } catch (Throwable) {
            return false;
        }
    }
}
