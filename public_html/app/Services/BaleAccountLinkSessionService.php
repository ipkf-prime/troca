<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use IPKF\Support\Clock;
use IPKF\Support\Session;

/**
 * Resolves the IPKF account from the CURRENT SERVER SESSION.
 *
 * Internal service only: no route, signing key, database write
 * or outgoing request is defined here.
 *
 * The eventual POST adapter must additionally enforce CSRF,
 * explicit user consent and trusted connector configuration.
 */
final class BaleAccountLinkSessionService
{
    /**
     * Read the actual IPKF authentication boundary.
     *
     * The external subject is derived from AuthService and
     * the existing session; it is never supplied by the browser.
     *
     * @return array{external_subject_id:string,
     *               session_authenticated_at:int}|null
     */
    public function currentVerifiedContext(): ?array
    {
        $auth = new AuthService();

        $userId = $auth->currentUserId();

        /*
         * currentUser() rechecks that the account still exists
         * and remains eligible for authentication.
         */
        $currentUser = $auth->currentUser();

        if (
            !is_int($userId) ||
            $userId <= 0 ||
            !is_array($currentUser)
        ) {
            return null;
        }

        $sessionUserId = Session::get('auth_user_id');
        $sessionMfa = Session::get('auth_mfa_verified');
        $sessionLoginAt = Session::get('auth_login_at');

        if (
            !is_int($sessionUserId) ||
            $sessionUserId !== $userId ||
            !is_string($sessionLoginAt) ||
            $sessionLoginAt === '' ||
            strlen($sessionLoginAt) > 80
        ) {
            return null;
        }

        /*
         * Use IPKF's own stored-time parser rather than
         * guessing the format of auth_login_at.
         */
        $authenticatedAt = Clock::parseStoredInstant(
            $sessionLoginAt
        );

        if (!$authenticatedAt instanceof DateTimeInterface) {
            return null;
        }

        return self::validateSnapshot(
            $userId,
            true,
            $sessionUserId,
            $sessionMfa,
            $authenticatedAt->getTimestamp(),
            time()
        );
    }

    /**
     * Pure decision boundary for offline synthetic tests.
     *
     * MFA policy is intentionally fail-closed for this initial
     * adapter: accounts without an explicit TRUE cannot link.
     * A later source-verified policy may distinguish users who
     * are not required to complete MFA.
     *
     * @return array{external_subject_id:string,
     *               session_authenticated_at:int}|null
     */
    public static function validateSnapshot(
        int $userId,
        bool $eligibleCurrentUser,
        mixed $sessionUserId,
        mixed $mfaVerified,
        mixed $authenticatedAt,
        int $now
    ): ?array {
        if (
            $userId <= 0 ||
            !$eligibleCurrentUser ||
            !is_int($sessionUserId) ||
            $sessionUserId !== $userId ||
            $mfaVerified !== true ||
            !is_int($authenticatedAt) ||
            $authenticatedAt <= 0 ||
            $now <= 0 ||
            $authenticatedAt > $now ||
            $authenticatedAt < $now - 300
        ) {
            return null;
        }

        return [
            'external_subject_id' => (string) $userId,
            'session_authenticated_at' => $authenticatedAt,
        ];
    }
}
