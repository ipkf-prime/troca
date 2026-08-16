<?php

namespace App\Services\Automation\Correspondence;

/**
 * Transport boundary for one correspondence dispatch attempt.
 *
 * Implementations may eventually bridge to SMTP, fax, system
 * APIs, courier APIs, etc.
 *
 * The correspondence lifecycle remains owned by Automation.
 */
interface CorrespondenceDispatchTransportInterface
{
    /**
     * Stable transport/provider code.
     *
     * Examples later may be:
     * - notification_gateway
     * - fax_provider
     * - postal_manual
     */
    public function code(): string;

    /**
     * Execute exactly one transport attempt.
     *
     * Expected result:
     *
     * [
     *   'outcome' => 'succeeded'|'failed'|'uncertain',
     *   'provider_reference' => ?string,
     *   'failure_code' => ?string,
     *   'failure_message' => ?string,
     *   'response_metadata' => array,
     * ]
     *
     * Throwing is treated as UNCERTAIN, not FAILED,
     * because transport acceptance may be unknown.
     */
    public function send(
        array $dispatch
    ): array;
}
