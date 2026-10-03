<?php

declare(strict_types=1);

namespace App\Services\Work;

use RuntimeException;

/**
 * TICKET_WORK_UI_CONTENT_V1
 *
 * New Ticket<->Work UI copy is data-driven. PHP consumers use stable keys;
 * user-visible copy lives in resources/ui-content/ticket-work-policy-ui.json.
 */
final class TicketWorkUiContentService
{
    private ?array $content = null;

    public function all(): array
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $path =
            dirname(__DIR__, 3)
            . '/resources/ui-content/ticket-work-policy-ui.json';

        $json = @file_get_contents($path);

        if (!is_string($json) || trim($json) === '') {
            throw new RuntimeException(
                'Ticket Work UI content is unavailable.'
            );
        }

        $decoded =
            json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Ticket Work UI content is invalid.'
            );
        }

        return $this->content = $decoded;
    }

    public function text(string $key): string
    {
        $value = $this->value($key);

        if (!is_string($value) && !is_numeric($value)) {
            throw new RuntimeException(
                'Ticket Work UI text key is unavailable: ' . $key
            );
        }

        return (string) $value;
    }

    public function options(string $key): array
    {
        $value = $this->value($key);

        return is_array($value)
            ? $value
            : [];
    }

    private function value(string $key): mixed
    {
        $cursor = $this->all();

        foreach (explode('.', trim($key)) as $segment) {
            if (
                $segment === ''
                || !is_array($cursor)
                || !array_key_exists($segment, $cursor)
            ) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
