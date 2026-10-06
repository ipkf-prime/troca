<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\UiContent\UiContentContext;
use App\Services\UiContent\UiContentResolver;

final class ImpersonationUiService
{
    private const KEYS = [
        'action' =>
            'core.users.impersonation.action',

        'confirm_title' =>
            'core.users.impersonation.confirm.title',

        'confirm_body' =>
            'core.users.impersonation.confirm.body',

        'banner' =>
            'core.users.impersonation.banner',

        'return' =>
            'core.users.impersonation.return',

        'readonly' =>
            'core.users.impersonation.readonly',

        'denied' =>
            'core.users.impersonation.denied',

        'expired' =>
            'core.users.impersonation.expired',
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
                    $item[
                        'body'
                    ]
                    ?? ''
                )
            );
    }


    public function ready(
        array $content
    ): bool {
        foreach (
            array_keys(
                self::KEYS
            )
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
