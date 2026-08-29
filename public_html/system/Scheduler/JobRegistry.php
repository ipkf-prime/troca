<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

use RuntimeException;

final class JobRegistry
{
    private array $jobs = [];


    public function register(
        SchedulerJobInterface $job
    ): void {
        $key =
            trim(
                $job->key()
            );

        if ($key === '') {
            throw new RuntimeException(
                'scheduler_job_key_required'
            );
        }

        if (isset($this->jobs[$key])) {
            throw new RuntimeException(
                'scheduler_job_duplicate:'
                . $key
            );
        }

        $this->jobs[$key] =
            $job;
    }


    public function get(
        string $key
    ): ?SchedulerJobInterface {
        return
            $this->jobs[$key]
            ?? null;
    }


    public function all(): array
    {
        return
            $this->jobs;
    }
}
