<?php

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class TicketStatusTitleManagementService
{
    private PDO $db;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function page(): array
    {
        $rows =
            $this->db->query("
                SELECT
                    code,
                    title,
                    category,
                    sort_order,
                    is_closed,
                    is_system,
                    is_active
                FROM ticketing_statuses
                ORDER BY sort_order, id
            ")->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        return [
            'statuses' =>
                $rows,
        ];
    }


    public function updateTitle(
        string $code,
        string $title
    ): array {
        $code =
            strtolower(
                trim($code)
            );

        $title =
            trim($title);

        if (
            preg_match(
                '/^[a-z][a-z0-9_]{0,63}$/',
                $code
            ) !== 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'status_title_invalid',
            ];
        }

        if ($title === '') {
            return [
                'ok' => false,
                'status' =>
                    'status_title_required',
            ];
        }

        $length =
            function_exists('mb_strlen')
                ? mb_strlen(
                    $title,
                    'UTF-8'
                )
                : strlen($title);

        if ($length > 120) {
            return [
                'ok' => false,
                'status' =>
                    'status_title_too_long',
            ];
        }

        if (
            preg_match(
                '/[\x{0600}-\x{06FF}]/u',
                $title
            ) !== 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'status_title_persian_required',
            ];
        }

        $exists =
            $this->db->prepare("
                SELECT code
                FROM ticketing_statuses
                WHERE code = ?
                LIMIT 1
            ");

        $exists->execute([
            $code,
        ]);

        if (
            $exists->fetchColumn()
            === false
        ) {
            return [
                'ok' => false,
                'not_found' => true,
                'status' =>
                    'status_title_not_found',
            ];
        }

        /*
         * TICKETING_STATUS_TITLE_MANAGEMENT
         *
         * Only the display title is mutable here.
         *
         * Immutable:
         * - code
         * - category
         * - sort_order
         * - is_closed
         * - is_system
         * - is_active
         */
        $update =
            $this->db->prepare("
                UPDATE ticketing_statuses
                SET title = ?
                WHERE code = ?
            ");

        $update->execute([
            $title,
            $code,
        ]);

        return [
            'ok' => true,
            'status' =>
                'status_title_updated',
            'code' =>
                $code,
            'title' =>
                $title,
        ];
    }
}
