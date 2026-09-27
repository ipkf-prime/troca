<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Canonical private filesystem locations for Bale account linking.
 *
 * Paths are derived from the application installation root and therefore
 * do not depend on a server username or a host-specific home directory.
 */
final class BaleAccountLinkPrivateStorage
{
    private const DIRECTORY_NAME = '.ipkf-bale-link-private';

    public static function rootDirectory(): string
    {
        return dirname(BASE_PATH)
            . DIRECTORY_SEPARATOR
            . self::DIRECTORY_NAME;
    }

    public static function configPath(): string
    {
        return self::rootDirectory()
            . DIRECTORY_SEPARATOR
            . 'config.json';
    }

    public static function outboxDirectory(): string
    {
        return self::rootDirectory()
            . DIRECTORY_SEPARATOR
            . 'outbox';
    }
}
