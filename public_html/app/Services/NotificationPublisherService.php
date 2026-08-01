<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use InvalidArgumentException;

class NotificationPublisherService extends BaseService
{
    public function __construct(
        private ?NotificationRepository $notifications = null
    ) {
        $this->notifications ??= new NotificationRepository();
    }

    public function publish(array $event): array
    {
        $eventType = $this->code(
            (string) ($event['event_type'] ?? ''),
            'event_type'
        );
        $sourceModule = $this->code(
            (string) ($event['source_module'] ?? ''),
            'source_module'
        );
        $sourceEntityType = $this->nullableCode(
            $event['source_entity_type'] ?? null
        );
        $sourceEntityReference = $this->nullableText(
            $event['source_entity_reference'] ?? null,
            190
        );
        $actorReference = $this->nullableText(
            $event['actor_user_reference'] ?? null,
            100
        );
        $title = trim((string) ($event['title'] ?? ''));
        $body = trim((string) ($event['body'] ?? ''));
        $templateCode = $this->nullableCode(
            $event['template_code'] ?? null
        );

        if ($title === '' && $templateCode === null) {
            throw new InvalidArgumentException(
                'notification_title_or_template_required'
            );
        }

        if ($body === '' && $templateCode === null) {
            throw new InvalidArgumentException(
                'notification_body_or_template_required'
            );
        }

        $recipients = $this->recipients(
            $event['recipient_user_references'] ?? []
        );

        if ($recipients === []) {
            throw new InvalidArgumentException(
                'notification_recipient_required'
            );
        }

        $channels = $this->channels(
            $event['channels'] ?? ['in_app']
        );
        $priority = $this->priority(
            (string) ($event['priority_code'] ?? 'normal')
        );
        $templateData = is_array(
            $event['template_data'] ?? null
        ) ? $event['template_data'] : [];
        $actionUrl = $this->nullableText(
            $event['action_url'] ?? null,
            1000
        );
        $category = $this->nullableCode(
            $event['category_code'] ?? null
        ) ?? 'general';
        $expiresAt = $this->nullableDateTime(
            $event['expires_at'] ?? null
        );
        $availableAt = $this->nullableDateTime(
            $event['available_at'] ?? null
        ) ?? gmdate('Y-m-d H:i:s');
        $occurredAt = $this->nullableDateTime(
            $event['occurred_at'] ?? null
        ) ?? gmdate('Y-m-d H:i:s');
        $maxAttempts = max(
            1,
            min(20, (int) ($event['max_attempts'] ?? 8))
        );

        $idempotencyKey = trim(
            (string) ($event['idempotency_key'] ?? '')
        );

        if ($idempotencyKey === '') {
            $idempotencyKey = 'notification:' . hash(
                'sha256',
                json_encode([
                    $eventType,
                    $sourceModule,
                    $sourceEntityType,
                    $sourceEntityReference,
                    $title,
                    $body,
                    $actionUrl,
                    $recipients,
                    $channels,
                    $event['source_event_reference'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'category_code' => $category,
            'priority_code' => $priority,
            'expires_at' => $expiresAt,
            'template_code' => $templateCode,
            'template_data' => $templateData,
            'locale' => (string) ($event['locale'] ?? 'fa'),
            'recipient_user_references' => $recipients,
            'channels' => $channels,
        ];

        $result = $this->notifications->createEventWithOutbox([
            'public_reference' => $this->reference('nev'),
            'outbox_reference' => $this->reference('nob'),
            'idempotency_key' => mb_substr(
                $idempotencyKey,
                0,
                190,
                'UTF-8'
            ),
            'event_type' => $eventType,
            'source_module' => $sourceModule,
            'source_entity_type' => $sourceEntityType,
            'source_entity_reference' => $sourceEntityReference,
            'actor_user_reference' => $actorReference,
            'payload_json' => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            'priority_code' => $priority,
            'available_at' => $availableAt,
            'occurred_at' => $occurredAt,
            'max_attempts' => $maxAttempts,
        ]);

        return [
            'ok' => true,
            'duplicate' => (bool) $result['duplicate'],
            'event_reference' =>
                $result['event_reference'],
            'outbox_reference' =>
                $result['outbox_reference'],
        ];
    }

    private function code(
        string $value,
        string $field
    ): string {
        $value = trim(strtolower($value));

        if (
            $value === ''
            || !preg_match(
                '/^[a-z0-9][a-z0-9._-]{1,99}$/',
                $value
            )
        ) {
            throw new InvalidArgumentException(
                'invalid_' . $field
            );
        }

        return $value;
    }

    private function nullableCode(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (!preg_match(
            '/^[a-z0-9][a-z0-9._-]{0,99}$/',
            strtolower($value)
        )) {
            throw new InvalidArgumentException(
                'invalid_notification_code'
            );
        }

        return strtolower($value);
    }

    private function nullableText(
        mixed $value,
        int $limit
    ): ?string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    private function recipients(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $reference = trim((string) $value);

            if ($reference === '') {
                continue;
            }

            $normalized[] = mb_substr(
                $reference,
                0,
                100,
                'UTF-8'
            );
        }

        return array_values(array_unique($normalized));
    }

    private function channels(mixed $values): array
    {
        if (!is_array($values)) {
            $values = ['in_app'];
        }

        $allowed = ['in_app', 'email', 'sms', 'bale'];
        $channels = array_values(array_unique(array_filter(
            array_map(
                static fn ($value): string =>
                    strtolower(trim((string) $value)),
                $values
            ),
            static fn (string $value): bool =>
                in_array($value, $allowed, true)
        )));

        return $channels !== [] ? $channels : ['in_app'];
    }

    private function priority(string $value): string
    {
        return in_array(
            $value,
            ['low', 'normal', 'high', 'urgent'],
            true
        ) ? $value : 'normal';
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false
            ? null
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function reference(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }
}
