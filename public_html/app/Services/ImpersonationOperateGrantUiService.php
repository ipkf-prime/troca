<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\UiContent\UiContentContext;
use App\Services\UiContent\UiContentResolver;

final class ImpersonationOperateGrantUiService
{
    private const KEYS = [
        'title' =>
            'core.users.impersonation.operate.access.title',

        'description' =>
            'core.users.impersonation.operate.access.description',

        'status_allowed' =>
            'core.users.impersonation.operate.access.status.allowed',

        'status_denied' =>
            'core.users.impersonation.operate.access.status.denied',

        'status_ineligible' =>
            'core.users.impersonation.operate.access.status.ineligible',

        'grant' =>
            'core.users.impersonation.operate.access.grant',

        'revoke' =>
            'core.users.impersonation.operate.access.revoke',

        'reason_label' =>
            'core.users.impersonation.operate.access.reason.label',

        'reason_placeholder' =>
            'core.users.impersonation.operate.access.reason.placeholder',

        'confirm_grant_title' =>
            'core.users.impersonation.operate.access.confirm.grant.title',

        'confirm_revoke_title' =>
            'core.users.impersonation.operate.access.confirm.revoke.title',

        'confirm_grant' =>
            'core.users.impersonation.operate.access.confirm.grant',

        'confirm_revoke' =>
            'core.users.impersonation.operate.access.confirm.revoke',

        'feedback_updated' =>
            'core.users.impersonation.operate.access.feedback.updated',

        'feedback_denied' =>
            'core.users.impersonation.operate.access.feedback.denied',
    ];

    private UiContentResolver $resolver;


    public function __construct(
        ?UiContentResolver $resolver = null
    ) {
        $this->resolver =
            $resolver
            ?? new UiContentResolver();
    }


    public function resolve(): array
    {
        $context =
            new UiContentContext(
                'core',
                'fa',
                [
                    [
                        'type' =>
                            'surface',

                        'reference' =>
                            'impersonation',
                    ],
                ]
            );

        $result = [];

        foreach (
            self::KEYS
            as $name => $key
        ) {
            $result[$name] =
                $this->resolver->resolve(
                    $key,
                    $context
                );
        }

        return $result;
    }


    public function text(
        array $content,
        string $name
    ): string {
        $item =
            $content[$name]
            ?? null;

        if (
            !is_array($item)
            || empty($item['available'])
            || empty($item['visible'])
        ) {
            return '';
        }

        return
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            );
    }


    public function ready(
        array $content
    ): bool {
        foreach (
            array_keys(self::KEYS)
            as $name
        ) {
            if (
                $this->text(
                    $content,
                    $name
                ) === ''
            ) {
                return false;
            }
        }

        return true;
    }
}
