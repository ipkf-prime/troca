<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\SupportProjectAdminRepository;
use App\Repositories\TicketAutoClosePolicyRepository;

final class TicketAutoClosePolicyAdminService
{
    public function __construct(
        private ?SupportProjectAdminRepository $projects = null,
        private ?TicketAutoClosePolicyRepository $policies = null
    ) {
        $this->projects ??=
            new SupportProjectAdminRepository();

        $this->policies ??=
            new TicketAutoClosePolicyRepository();
    }


    public function save(
        string $projectReference,
        array $input,
        int $userId
    ): array {
        $projectReference =
            trim(
                $projectReference
            );

        if (
            $projectReference === ''
            ||
            $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'auto_close_request_invalid',
            ];
        }


        $project =
            $this->projects
                ->findByReference(
                    $projectReference
                );

        if (
            !is_array($project)
            ||
            !empty(
                $project[
                    'archived_at'
                ]
            )
        ) {
            return [
                'ok' => false,
                'status' =>
                    'auto_close_project_not_found',
            ];
        }


        $enabled =
            !empty(
                $input[
                    'is_enabled'
                ]
            );

        $rawDelay =
            trim(
                (string) (
                    $input[
                        'delay_hours'
                    ]
                    ?? ''
                )
            );

        $delayHours = null;


        if ($rawDelay !== '') {

            if (
                preg_match(
                    '/^[0-9]+$/',
                    $rawDelay
                ) !== 1
            ) {
                return [
                    'ok' => false,
                    'status' =>
                        'auto_close_delay_invalid',
                ];
            }

            $delayHours =
                (int) $rawDelay;

            if (
                $delayHours < 1
                ||
                $delayHours > 65535
            ) {
                return [
                    'ok' => false,
                    'status' =>
                        'auto_close_delay_invalid',
                ];
            }
        }


        if (
            $enabled
            &&
            $delayHours === null
        ) {
            return [
                'ok' => false,
                'status' =>
                    'auto_close_delay_required',
            ];
        }


        $policy =
            $this->policies
                ->savePolicy(
                    (int) $project['id'],
                    $enabled,
                    $delayHours,
                    'user:' . $userId
                );


        return [
            'ok' => true,

            'status' =>
                !empty(
                    $policy[
                        'is_enabled'
                    ]
                )
                    ? 'auto_close_enabled'
                    : 'auto_close_disabled',

            'policy' =>
                $policy,
        ];
    }
}
