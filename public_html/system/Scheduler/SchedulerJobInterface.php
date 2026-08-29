<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

interface SchedulerJobInterface
{
    public function key(): string;

    public function applicationKey(): string;

    public function title(): string;

    public function description(): string;

    public function scopeModel(): string;

    public function defaultIntervalMinutes(): int;

    public function scopes(): array;

    public function run(array $context): array;
}
