<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use IPKF\Support\PersianDate;
use Throwable;

class NotificationInboxService extends BaseService
{
    private const PER_PAGE = 20;

    public function __construct(
        private ?NotificationRepository $notifications = null
    ) {
        $this->notifications ??= new NotificationRepository();
    }

    public function page(
        int $userId,
        array $params = []
    ): array {
        $filter = (string) ($params['filter'] ?? 'all');
        $filter = in_array(
            $filter,
            ['all', 'unread'],
            true
        ) ? $filter : 'all';
        $page = max(
            1,
            min(10000, (int) ($params['page'] ?? 1))
        );

        try {
            $result = $this->notifications->inbox(
                $userId,
                $filter,
                $page,
                self::PER_PAGE
            );

            $unread = $this->notifications->unreadCount(
                $userId
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'items' => [],
                'filter' => $filter,
                'unread_count' => 0,
                'pagination' => $this->pagination(0, 1),
            ];
        }

        $total = (int) ($result['total'] ?? 0);
        $lastPage = max(
            1,
            (int) ceil($total / self::PER_PAGE)
        );
        $page = min($page, $lastPage);

        return [
            'ok' => true,
            'items' => array_map(
                fn (array $row): array =>
                    $this->item($row),
                $result['items'] ?? []
            ),
            'filter' => $filter,
            'unread_count' => $unread,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'last_page' => $lastPage,
                'has_previous' => $page > 1,
                'previous_page' => max(1, $page - 1),
                'has_next' => $page < $lastPage,
                'next_page' => min($lastPage, $page + 1),
            ],
        ];
    }

    public function unreadCount(int $userId): int
    {
        try {
            return $this->notifications->unreadCount(
                $userId
            );
        } catch (Throwable) {
            return 0;
        }
    }

    public function markRead(
        int $userId,
        string $reference
    ): bool {
        try {
            return $this->notifications->markRead(
                $userId,
                $reference
            );
        } catch (Throwable) {
            return false;
        }
    }

    public function markActionRead(
        int $userId,
        string $actionUrl
    ): int {
        try {
            return $this->notifications->markActionRead(
                $userId,
                $actionUrl
            );
        } catch (Throwable) {
            return 0;
        }
    }

    public function markAllRead(int $userId): int
    {
        try {
            return $this->notifications->markAllRead(
                $userId
            );
        } catch (Throwable) {
            return 0;
        }
    }

    private function item(array $row): array
    {
        $createdAt = (string) ($row['created_at'] ?? '');
        $timestamp = strtotime($createdAt);

        return [
            'reference' =>
                (string) $row['public_reference'],
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'action_url' =>
                trim((string) ($row['action_url'] ?? '')),
            'priority_code' =>
                (string) ($row['priority_code'] ?? 'normal'),
            'category_code' =>
                (string) ($row['category_code'] ?? 'general'),
            'created_at' => $timestamp === false
                ? $createdAt
                : PersianDate::fromGregorianDate(
                    date('Y-m-d', $timestamp)
                ) . ' - ' . \App\Support\AdminFormat::digits(
                    date('H:i', $timestamp)
                ),
            'is_read' => !empty($row['read_at']),
        ];
    }

    private function pagination(
        int $total,
        int $page
    ): array {
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'last_page' => 1,
            'has_previous' => false,
            'previous_page' => 1,
            'has_next' => false,
            'next_page' => 1,
        ];
    }
}
