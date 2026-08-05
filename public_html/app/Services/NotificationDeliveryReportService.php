<?php

namespace App\Services;

use App\Repositories\NotificationDeliveryReportRepository;
use IPKF\Support\PersianDate;
use RuntimeException;
use Throwable;

class NotificationDeliveryReportService extends BaseService
{
    private const CHANNELS = [
        '',
        'in_app',
        'email',
        'sms',
        'messenger',
    ];

    private const STATUSES = [
        '',
        'pending',
        'queued',
        'processing',
        'sent',
        'delivered',
        'failed',
        'cancelled',
    ];

    private const SORTS = [
        'created_desc',
        'created_asc',
        'status_asc',
        'status_desc',
        'channel_asc',
        'attempts_desc',
        'attempts_asc',
    ];

    private const PAGE_SIZES = [20, 50, 100];

    public function __construct(
        private ?NotificationDeliveryReportRepository $repository = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??= new NotificationDeliveryReportRepository();
        $this->authorization ??= new AuthorizationService();
    }

    public function page(int $userId, array $input): array
    {
        if (!$this->authorization->hasPermission(
            $userId,
            'notifications.reports.view'
        )) {
            throw new RuntimeException(
                'notification_reports_forbidden'
            );
        }

        $filters = $this->filters($input);
        $page = $this->repository->page($filters);
        $page['filters'] = $filters;
        $page['items'] = $this->sanitizeItems(
            is_array($page['items'] ?? null)
                ? $page['items']
                : []
        );

        return $page;
    }

    private function filters(array $input): array
    {
        $query = trim((string) ($input['q'] ?? ''));

        if (mb_strlen($query, 'UTF-8') > 190) {
            $query = mb_substr($query, 0, 190, 'UTF-8');
        }

        $channel = strtolower(trim(
            (string) ($input['channel'] ?? '')
        ));
        $status = strtolower(trim(
            (string) ($input['status'] ?? '')
        ));
        $provider = strtolower(trim(
            (string) ($input['provider'] ?? '')
        ));
        $sort = strtolower(trim(
            (string) ($input['sort'] ?? 'created_desc')
        ));

        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = '';
        }

        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        if (
            $provider !== ''
            && preg_match(
                '/^[a-z0-9._-]{1,100}$/',
                $provider
            ) !== 1
        ) {
            $provider = '';
        }

        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'created_desc';
        }

        $perPage = (int) ($input['per_page'] ?? 20);

        if (!in_array($perPage, self::PAGE_SIZES, true)) {
            $perPage = 20;
        }

        [$fromInput, $from] =
            $this->dateFilter($input['from'] ?? '');
        [$toInput, $to] =
            $this->dateFilter($input['to'] ?? '');

        if (
            $from !== ''
            && $to !== ''
            && strcmp($from, $to) > 0
        ) {
            [$from, $to] = [$to, $from];
            [$fromInput, $toInput] = [$toInput, $fromInput];
        }

        return [
            'q' => $query,
            'channel' => $channel,
            'status' => $status,
            'provider' => $provider,
            'from' => $from,
            'to' => $to,
            'from_input' => $fromInput,
            'to_input' => $toInput,
            'sort' => $sort,
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'per_page' => $perPage,
        ];
    }

    private function dateFilter(mixed $value): array
    {
        $input = trim((string) $value);

        if ($input === '') {
            return ['', ''];
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $input
            ) === 1
        ) {
            return [$input, $input];
        }

        try {
            $gregorian = (string) (
                PersianDate::toGregorianDate($input) ?? ''
            );
        } catch (Throwable) {
            return [$input, ''];
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $gregorian
            ) !== 1
        ) {
            return [$input, ''];
        }

        return [$input, $gregorian];
    }

    private function sanitizeItems(array $items): array
    {
        foreach ($items as &$item) {
            $attempts = is_array($item['attempts'] ?? null)
                ? $item['attempts']
                : [];

            foreach ($attempts as &$attempt) {
                $metadata = json_decode(
                    (string) (
                        $attempt['response_metadata_json']
                        ?? ''
                    ),
                    true
                );

                $attempt['metadata'] = $this->sanitizeMetadata(
                    is_array($metadata) ? $metadata : []
                );

                unset($attempt['response_metadata_json']);
            }
            unset($attempt);

            $item['attempts'] = $attempts;
        }
        unset($item);

        return $items;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;

            if (preg_match(
                '/secret|token|password|api[_-]?key|authorization|credential/i',
                $key
            ) === 1) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitizeMetadata($value);
                continue;
            }

            if (
                is_bool($value)
                || is_int($value)
                || is_float($value)
                || $value === null
            ) {
                $safe[$key] = $value;
                continue;
            }

            $text = trim((string) $value);

            $safe[$key] = mb_strlen($text, 'UTF-8') > 500
                ? mb_substr($text, 0, 500, 'UTF-8') . '…'
                : $text;
        }

        return $safe;
    }
}
