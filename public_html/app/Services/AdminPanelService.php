<?php

namespace App\Services;

use IPKF\Support\Session;
use IPKF\Support\Version;

class AdminPanelService extends BaseService
{
    public function __construct(
        protected ?AuthService $auth = null,
        protected ?AccessService $access = null,
        protected ?MfaService $mfa = null
    ) {
        $this->auth ??= new AuthService();
        $this->access ??= new AccessService();
        $this->mfa ??= new MfaService();
    }

    public function context(): ?array
    {
        $user = $this->auth->currentUser();
        $userId = $this->auth->currentUserId();

        if ($user === null || $userId === null) {
            return null;
        }

        $methods = array_values(array_unique(array_map(
            fn (array $method): string => (string) $method['method'],
            $this->mfa->methodsForUser($userId)
        )));

        return [
            'user' => $user,
            'user_id' => $userId,
            'assignments' => $this->access->assignments($userId),
            'active_assignment' => $this->access->activeAssignment($userId),
            'mfa' => [
                'enabled' => $this->mfa->enabled() && $methods !== [],
                'verified' => (bool) Session::get('auth_mfa_verified', false),
                'methods' => $methods,
                'recovery_codes_available' => $this->mfa->recoveryCodesAvailable($userId),
            ],
            'version' => Version::CURRENT,
        ];
    }
}
