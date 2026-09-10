<?php

declare(strict_types=1);

namespace App\Services\UiContent;


/**
 * Platform-wide module-specific UI Content scope provider.
 *
 * Core understands only a generic hierarchical scope.
 * Each module owns the semantics of its deeper scopes.
 */
interface UiContentScopeProviderInterface
{
    public function moduleKey(): string;


    public function resolveRequestScope(
        string $host,
        string $uri
    ): array;


    /**
     * Validation only. This does not grant authorization.
     */
    public function normalizeScopePath(
        array $scopePath
    ): array;


    /**
     * Presentation description of an already-authorized
     * explicit scope.
     *
     * Must remain read-only.
     */
    public function describeScopePath(
        array $scopePath
    ): array;


    public function scopeCatalog(
        array $parentScopePath = []
    ): array;
}
