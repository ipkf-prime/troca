<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use InvalidArgumentException;

/**
 * PROJECT_SOURCE_CONFIGURATION_VALIDATOR_V1
 *
 * Validates project-source configuration without knowing a concrete
 * adapter, transport, or storage implementation.
 *
 * Secret material is forbidden in connector_config. Integrations must
 * use credential_reference for indirection.
 */
final class ProjectSourceConfigurationValidator
{
    private const STATUS = [
        'inactive',
        'active',
        'disabled',
    ];

    private const FORBIDDEN_SECRET_KEYS = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
        'credential',
        'credentials',
    ];

    public function normalize(
        array $config
    ): array {
        $code =
            $this->asciiCode(
                $config['code']
                ?? null,
                100,
                'code'
            );

        $title =
            trim(
                (string) (
                    $config['title']
                    ?? ''
                )
            );

        if ($title === '') {
            throw new InvalidArgumentException(
                'Project source title is required.'
            );
        }

        $driverCode =
            $this->asciiCode(
                $config['driver_code']
                ?? null,
                64,
                'driver_code'
            );

        $catalogCode =
            $this->asciiCode(
                $config['catalog_code']
                ?? null,
                100,
                'catalog_code'
            );

        $dimensionCode =
            $this->asciiCode(
                $config['dimension_code']
                ?? null,
                100,
                'dimension_code'
            );

        $credentialReference =
            $this->nullableAsciiReference(
                $config[
                    'credential_reference'
                ]
                ?? null
            );

        $connectorConfig =
            $config[
                'connector_config'
            ]
            ?? [];

        if (!is_array($connectorConfig)) {
            throw new InvalidArgumentException(
                'connector_config must be an array.'
            );
        }

        $this->assertNoSecrets(
            $connectorConfig
        );

        $status =
            strtolower(
                trim(
                    (string) (
                        $config['status']
                        ?? 'inactive'
                    )
                )
            );

        if (
            !in_array(
                $status,
                self::STATUS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid project source status.'
            );
        }

        return [
            'code' =>
                $code,

            'title' =>
                $title,

            'driver_code' =>
                $driverCode,

            'catalog_code' =>
                $catalogCode,

            'dimension_code' =>
                $dimensionCode,

            'credential_reference' =>
                $credentialReference,

            'connector_config' =>
                $connectorConfig,

            'status' =>
                $status,
        ];
    }

    private function asciiCode(
        mixed $value,
        int $maxLength,
        string $field
    ): string {
        $value =
            strtolower(
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
            );

        if (
            $value === ''
            || strlen($value) > $maxLength
            || preg_match(
                '/^[a-z0-9][a-z0-9._-]*$/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid project source '
                . $field
                . '.'
            );
        }

        return $value;
    }

    private function nullableAsciiReference(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        if (
            strlen($value) > 190
            || preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid credential_reference.'
            );
        }

        return $value;
    }

    private function assertNoSecrets(
        array $value,
        string $path = 'connector_config'
    ): void {
        foreach ($value as $key => $child) {
            $normalizedKey =
                strtolower(
                    trim(
                        (string) $key
                    )
                );

            if (
                in_array(
                    $normalizedKey,
                    self::FORBIDDEN_SECRET_KEYS,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Secret-like key is forbidden in '
                    . $path
                    . '.'
                );
            }

            if (is_array($child)) {
                $this->assertNoSecrets(
                    $child,
                    $path
                    . '.'
                    . $normalizedKey
                );
            }
        }
    }
}
