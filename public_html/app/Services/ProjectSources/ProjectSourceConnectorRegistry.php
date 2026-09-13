<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use InvalidArgumentException;
use LogicException;

/**
 * PROJECT_SOURCE_CONNECTOR_REGISTRY_V1
 */
final class ProjectSourceConnectorRegistry
{
    private array $connectors = [];

    public function __construct(
        iterable $connectors = []
    ) {
        foreach ($connectors as $connector) {
            if (
                !$connector instanceof
                    ProjectSourceConnectorInterface
            ) {
                throw new InvalidArgumentException(
                    'Invalid project source connector.'
                );
            }

            $this->register(
                $connector
            );
        }
    }

    public function register(
        ProjectSourceConnectorInterface $connector
    ): void {
        $driver =
            strtolower(
                trim(
                    $connector->driverCode()
                )
            );

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{1,63}$/',
                $driver
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid project source connector driver code.'
            );
        }

        if (
            isset(
                $this->connectors[
                    $driver
                ]
            )
        ) {
            throw new LogicException(
                'Duplicate project source connector driver.'
            );
        }

        $this->connectors[$driver] =
            $connector;
    }

    public function has(
        string $driver
    ): bool {
        return
            isset(
                $this->connectors[
                    strtolower(
                        trim($driver)
                    )
                ]
            );
    }

    public function get(
        string $driver
    ): ?ProjectSourceConnectorInterface {
        return
            $this->connectors[
                strtolower(
                    trim($driver)
                )
            ]
            ?? null;
    }

    public function drivers(): array
    {
        $drivers =
            array_keys(
                $this->connectors
            );

        sort(
            $drivers,
            SORT_STRING
        );

        return $drivers;
    }
}
