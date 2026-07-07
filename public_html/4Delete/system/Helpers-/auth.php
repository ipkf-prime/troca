<?php

use IPKF\Auth\Auth;
use IPKF\Auth\Authorization;

function auth(): Auth
{
    return new Auth();
}

function can(string $permission): bool
{
    $auth = new Authorization();

    $user = auth()->user();

    if (!$user) {
        return false;
    }

    return $auth->hasPermission($user['id'], $permission);
}