<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use DateTimeImmutable;
use RuntimeException;
use Throwable;


/**
 * T3F_C1_UI_CONTENT_RESOLVER_V1
 *
 * Sparse field-level cascade:
 *
 * global
 *   -> module
 *      -> scope[0]
 *         -> scope[1]
 *            -> ...
 *
 * At the same scope:
 *
 * default locale
 *   -> requested locale
 *
 * More-specific values only override fields that are
 * explicitly non-null.
 */
final class UiContentResolver
{
    private ?UiContentStoreInterface $store;
    private UiContentEmergencyCatalog $emergency;


    public function __construct(
        ?UiContentStoreInterface $store = null,
        ?UiContentEmergencyCatalog $emergency = null
    ) {
        /*
         * IMPORTANT:
         *
         * Do NOT eagerly construct UiContentRepository here.
         * Otherwise a DB/configuration failure can prevent the
         * emergency error presenter itself from being created.
         */
        $this->store =
            $store;

        $this->emergency =
            $emergency
            ?? new UiContentEmergencyCatalog();
    }


    public function resolve(
        string $contentKey,
        UiContentContext $context,
        ?int $fallbackHttpStatus = null
    ): array {

        $contentKey =
            strtolower(
                trim(
                    $contentKey
                )
            );

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{2,189}$/D',
                $contentKey
            )
            !== 1
        ) {
            throw new RuntimeException(
                'ui_content_key_invalid'
            );
        }


        try {

            $store =
                $this->store
                ?? new UiContentRepository();

            if (!$store->available()) {
                return
                    $this->fallback(
                        $contentKey,
                        $context,
                        $fallbackHttpStatus,
                        'store_unavailable'
                    );
            }

            $definition =
                $store->definition(
                    $contentKey
                );

        } catch (Throwable) {

            return
                $this->fallback(
                    $contentKey,
                    $context,
                    $fallbackHttpStatus,
                    'store_failure'
                );
        }


        if (!is_array($definition)) {

            return
                $this->fallback(
                    $contentKey,
                    $context,
                    $fallbackHttpStatus,
                    'definition_missing'
                );
        }


        $definitionId =
            (int) (
                $definition['id']
                ?? 0
            );

        if ($definitionId < 1) {
            throw new RuntimeException(
                'ui_content_definition_invalid'
            );
        }


        $defaultLocale =
            strtolower(
                trim(
                    (string) (
                        $definition[
                            'default_locale'
                        ]
                        ?? 'fa'
                    )
                )
            );

        if (
            preg_match(
                '/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/D',
                $defaultLocale
            )
            !== 1
        ) {
            $defaultLocale = 'fa';
        }


        $requestedLocale =
            $context->locale();

        $locales =
            array_values(
                array_unique([
                    $defaultLocale,
                    $requestedLocale,
                ])
            );


        $httpStatus =
            isset(
                $definition[
                    'http_status'
                ]
            )
                ? (int) $definition[
                    'http_status'
                ]
                : $fallbackHttpStatus;


        $base =
            $httpStatus !== null
                ? $this->emergency
                    ->forHttpStatus(
                        $httpStatus
                    )
                : [
                    'available' =>
                        false,

                    'visible' =>
                        true,

                    'title' =>
                        null,

                    'body' =>
                        null,

                    'icon_code' =>
                        null,

                    'severity_code' =>
                        null,

                    'layout_variant' =>
                        null,

                    'primary_action_code' =>
                        null,

                    'primary_action_label' =>
                        null,

                    'secondary_action_code' =>
                        null,

                    'secondary_action_label' =>
                        null,

                    'metadata' =>
                        [],

                    'source' =>
                        null,
                ];


        try {

            $rows =
                $store->activeOverrides(
                    $definitionId,
                    $context
                        ->cascadeScopeKeys(),
                    $locales,
                    new DateTimeImmutable()
                );

        } catch (Throwable) {

            return
                $this->fallback(
                    $contentKey,
                    $context,
                    $httpStatus,
                    'override_store_failure'
                );
        }


        $byIdentity = [];

        foreach ($rows as $row) {

            if (!is_array($row)) {
                continue;
            }

            $scopeKey =
                trim(
                    (string) (
                        $row['scope_key']
                        ?? ''
                    )
                );

            $locale =
                strtolower(
                    trim(
                        (string) (
                            $row['locale']
                            ?? ''
                        )
                    )
                );

            if (
                $scopeKey === ''
                ||
                $locale === ''
            ) {
                continue;
            }

            $byIdentity[
                $scopeKey
                . '|'
                . $locale
            ] = $row;
        }


        $result = $base;

        $result['content_key'] =
            $contentKey;

        $result['content_type'] =
            (string) (
                $definition[
                    'content_type'
                ]
                ?? ''
            );

        $result['http_status'] =
            $httpStatus;

        $result['requested_locale'] =
            $requestedLocale;

        $result['default_locale'] =
            $defaultLocale;

        $result['module_key'] =
            $context->moduleKey();

        $result['scope_path'] =
            $context->scopePath();

        $result['resolved_scope_key'] =
            null;

        $result['resolution_trace'] =
            [];


        $applied = 0;

        foreach (
            $context->cascadeScopeKeys()
            as $scopeKey
        ) {

            /*
             * At the same scope, requested locale overlays
             * default locale.
             */
            $scopeLocales =
                $defaultLocale
                === $requestedLocale
                    ? [
                        $defaultLocale,
                    ]
                    : [
                        $defaultLocale,
                        $requestedLocale,
                    ];

            foreach ($scopeLocales as $locale) {

                $identity =
                    $scopeKey
                    . '|'
                    . $locale;

                $row =
                    $byIdentity[
                        $identity
                    ]
                    ?? null;

                if (!is_array($row)) {
                    continue;
                }

                $this->applyRow(
                    $result,
                    $row
                );

                $applied++;

                $result[
                    'available'
                ] = true;

                $result[
                    'resolved_scope_key'
                ] = $scopeKey;

                $result[
                    'resolution_trace'
                ][] = [
                    'scope_key' =>
                        $scopeKey,

                    'locale' =>
                        $locale,

                    'override_reference' =>
                        (string) (
                            $row[
                                'public_reference'
                            ]
                            ?? ''
                        ),
                ];
            }
        }


        if (
            $applied === 0
            &&
            $httpStatus === null
        ) {
            $result['available'] = false;
        }


        $result['source'] =
            $applied > 0
                ? 'dynamic_override_cascade'
                : (
                    $result['source']
                    ?? null
                );


        return $result;
    }


    private function applyRow(
        array &$result,
        array $row
    ): void {

        foreach ([
            'title',
            'body',
            'icon_code',
            'severity_code',
            'layout_variant',
            'primary_action_code',
            'primary_action_label',
            'secondary_action_code',
            'secondary_action_label',
        ] as $field) {

            if (
                array_key_exists(
                    $field,
                    $row
                )
                &&
                $row[$field] !== null
            ) {
                $result[$field] =
                    $row[$field];
            }
        }


        $visibility =
            strtolower(
                trim(
                    (string) (
                        $row[
                            'visibility_mode'
                        ]
                        ?? 'inherit'
                    )
                )
            );

        if ($visibility === 'show') {
            $result['visible'] = true;
        }

        if ($visibility === 'hide') {
            $result['visible'] = false;
        }


        if (
            array_key_exists(
                'metadata_json',
                $row
            )
            &&
            $row[
                'metadata_json'
            ] !== null
        ) {

            $decoded =
                json_decode(
                    (string) $row[
                        'metadata_json'
                    ],
                    true
                );

            if (is_array($decoded)) {

                $result['metadata'] =
                    array_replace(
                        is_array(
                            $result[
                                'metadata'
                            ]
                            ?? null
                        )
                            ? $result[
                                'metadata'
                            ]
                            : [],
                        $decoded
                    );
            }
        }
    }


    private function fallback(
        string $contentKey,
        UiContentContext $context,
        ?int $httpStatus,
        string $reason
    ): array {

        if ($httpStatus === null) {

            return [
                'available' =>
                    false,

                'visible' =>
                    false,

                'content_key' =>
                    $contentKey,

                'content_type' =>
                    null,

                'http_status' =>
                    null,

                'requested_locale' =>
                    $context->locale(),

                'default_locale' =>
                    null,

                'module_key' =>
                    $context->moduleKey(),

                'scope_path' =>
                    $context->scopePath(),

                'resolved_scope_key' =>
                    null,

                'resolution_trace' =>
                    [],

                'source' =>
                    'unavailable',

                'fallback_reason' =>
                    $reason,
            ];
        }


        $result =
            $this->emergency
                ->forHttpStatus(
                    $httpStatus
                );

        $result['content_key'] =
            $contentKey;

        $result['content_type'] =
            'error';

        $result['http_status'] =
            $httpStatus;

        $result['requested_locale'] =
            $context->locale();

        $result['default_locale'] =
            null;

        $result['module_key'] =
            $context->moduleKey();

        $result['scope_path'] =
            $context->scopePath();

        $result['resolved_scope_key'] =
            null;

        $result['resolution_trace'] =
            [];

        $result['fallback_reason'] =
            $reason;

        return $result;
    }
}
