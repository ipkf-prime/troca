<?php

namespace App\Services\Otp;

interface OtpChannelInterface
{
    public function channel(): string;

    public function send(int $userId, string $destination, string $code): bool;
}
