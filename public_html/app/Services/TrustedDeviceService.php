<?php

namespace App\Services;

use App\Repositories\TrustedDeviceRepository;

class TrustedDeviceService extends BaseService
{
    public function __construct(protected ?TrustedDeviceRepository $devices = null)
    {
        $this->devices ??= new TrustedDeviceRepository();
    }

    public function listForUser(int $userId): array
    {
        return $this->devices->listForUser($userId);
    }

    public function hasActiveTrustedDevice(int $userId): bool
    {
        return $this->devices->activeCountForUser($userId) > 0;
    }

    public function revoke(int $userId, int $deviceId): bool
    {
        return $this->devices->revoke($userId, $deviceId);
    }
}
