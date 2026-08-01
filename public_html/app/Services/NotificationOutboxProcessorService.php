<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use RuntimeException;
use Throwable;

class NotificationOutboxProcessorService extends BaseService
{
    public function __construct(
        private ?NotificationRepository $notifications = null,
        private ?NotificationTemplateRendererService $renderer = null
    ) {
        $this->notifications ??= new NotificationRepository();
        $this->renderer ??=
            new NotificationTemplateRendererService();
    }

    public function process(
        int $limit = 50,
        ?string $workerId = null
    ): array {
        $limit = max(1, min(200, $limit));
        $workerId = trim((string) $workerId);

        if ($workerId === '') {
            $workerId = gethostname()
                . ':'
                . getmypid();
        }

        $processed = 0;
        $failed = 0;
        $duplicates = 0;

        for ($index = 0; $index < $limit; $index++) {
            $outbox = $this->notifications
                ->claimNextOutbox($workerId);

            if ($outbox === null) {
                break;
            }

            try {
                $event = $this->notifications->eventById(
                    (int) $outbox['event_id']
                );

                if ($event === null) {
                    throw new RuntimeException(
                        'notification_event_missing'
                    );
                }

                $payload = json_decode(
                    (string) $event['payload_json'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (!is_array($payload)) {
                    throw new RuntimeException(
                        'notification_payload_invalid'
                    );
                }

                $content = $this->content($payload);

                $result = $this->notifications
                    ->materializeInApp(
                        $event,
                        $content,
                        $this->recipients($payload),
                        $this->channels($payload)
                    );

                if (($result['recipient_count'] ?? 0) < 1) {
                    throw new RuntimeException(
                        'notification_recipient_missing'
                    );
                }

                $this->notifications->completeOutbox(
                    (int) $outbox['id']
                );
                $processed++;
            } catch (Throwable $exception) {
                $this->notifications->failOutbox(
                    (int) $outbox['id'],
                    (int) $outbox['attempts_count'],
                    (int) $outbox['max_attempts'],
                    $exception->getMessage()
                );
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'duplicates' => $duplicates,
        ];
    }

    private function content(array $payload): array
    {
        $templateCode = trim(
            (string) ($payload['template_code'] ?? '')
        );
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $actionUrl = trim(
            (string) ($payload['action_url'] ?? '')
        );

        if ($templateCode !== '') {
            $template = $this->notifications->template(
                $templateCode,
                'in_app',
                (string) ($payload['locale'] ?? 'fa')
            );

            if ($template !== null) {
                $data = is_array(
                    $payload['template_data'] ?? null
                ) ? $payload['template_data'] : [];
                $data += [
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $actionUrl,
                ];

                $title = $this->renderer->render(
                    (string) (
                        $template['title_template'] ?? ''
                    ),
                    $data
                );
                $body = $this->renderer->render(
                    (string) $template['body_template'],
                    $data
                );
                $actionUrl = $this->renderer->render(
                    (string) (
                        $template['action_url_template'] ?? ''
                    ),
                    $data
                );
            }
        }

        if ($title === '' || $body === '') {
            throw new RuntimeException(
                'notification_content_missing'
            );
        }

        return [
            'public_reference' =>
                'ntf_' . bin2hex(random_bytes(12)),
            'template_code' =>
                $templateCode !== '' ? $templateCode : null,
            'title' => mb_substr($title, 0, 500, 'UTF-8'),
            'body' => $body,
            'action_url' =>
                $actionUrl !== '' ? $actionUrl : null,
            'priority_code' => in_array(
                $payload['priority_code'] ?? 'normal',
                ['low', 'normal', 'high', 'urgent'],
                true
            ) ? $payload['priority_code'] : 'normal',
            'category_code' => trim(
                (string) (
                    $payload['category_code'] ?? 'general'
                )
            ) ?: 'general',
            'expires_at' =>
                $payload['expires_at'] ?? null,
        ];
    }

    private function recipients(array $payload): array
    {
        $values = $payload[
            'recipient_user_references'
        ] ?? [];

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn ($value): string =>
                    trim((string) $value),
                $values
            ),
            static fn (string $value): bool =>
                $value !== ''
        )));
    }

    private function channels(array $payload): array
    {
        $values = $payload['channels'] ?? ['in_app'];

        if (!is_array($values)) {
            return ['in_app'];
        }

        $allowed = $this->notifications
            ->activeChannelCodes();
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
}
