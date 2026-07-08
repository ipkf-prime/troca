<?php

namespace IPKF\Installer;

use IPKF\Support\Version;

class Installer
{
    public function __construct(
        protected InstallationState $state = new InstallationState(),
        protected RequirementChecker $requirements = new RequirementChecker(),
        protected EnvironmentChecker $environment = new EnvironmentChecker(),
        protected DatabaseChecker $database = new DatabaseChecker()
    ) {
    }

    public function state(): InstallationState
    {
        return $this->state;
    }

    public function payload(): array
    {
        return [
            'installer' => 'IPKF Installer',
            'version' => Version::CURRENT,
            'installed' => $this->state->installed(),
            'checks' => array_merge(
                $this->requirements->check(),
                $this->environment->check(),
                $this->database->check()
            ),
        ];
    }
}
