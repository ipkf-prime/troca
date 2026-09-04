<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use IPKF\Database\Database;
use IPKF\Support\Clock;
use PDO;

class SmsDeliveryPolicyService extends BaseService
{
    private const NAMESPACE =
        'communications.sms_policy';

    private const DEFAULT_ALL_DAY =
        false;

    private const DEFAULT_START =
        '07:00';

    private const DEFAULT_END =
        '22:00';

    private ?array $cachedSettings = null;

    public function settings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $statement =
            Database::connect()->prepare("
                SELECT
                    setting_key,
                    setting_value
                FROM app_settings
                WHERE user_id = 0
                  AND namespace = ?
            ");

        $statement->execute([
            self::NAMESPACE,
        ]);

        $values = [];

        foreach (
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: []
            as $row
        ) {
            $key = trim(
                (string) (
                    $row['setting_key']
                    ?? ''
                )
            );

            if ($key === '') {
                continue;
            }

            $values[$key] =
                (string) (
                    $row['setting_value']
                    ?? ''
                );
        }

        $start =
            $this->normalizeTime(
                $values['start_time']
                ?? self::DEFAULT_START,
                self::DEFAULT_START
            );

        $end =
            $this->normalizeTime(
                $values['end_time']
                ?? self::DEFAULT_END,
                self::DEFAULT_END
            );

        $this->cachedSettings = [
            'all_day' =>
                $this->boolean(
                    $values['all_day']
                    ?? (
                        self::DEFAULT_ALL_DAY
                            ? '1'
                            : '0'
                    )
                ),

            'start_time' =>
                $start,

            'end_time' =>
                $end,

            'timezone' =>
                Clock::displayTimezoneName(),

            'namespace' =>
                self::NAMESPACE,
        ];

        return $this->cachedSettings;
    }

    public function decision(
        ?DateTimeImmutable $now = null
    ): array {
        $settings =
            $this->settings();

        $localNow =
            ($now ?? Clock::nowUtc())
                ->setTimezone(
                    Clock::displayTimezone()
                );

        if (
            (bool) (
                $settings['all_day']
                ?? false
            )
        ) {
            return [
                'allowed' => true,
                'status' =>
                    'sms_policy_allowed',
                'all_day' => true,
                'start_time' =>
                    $settings['start_time'],
                'end_time' =>
                    $settings['end_time'],
                'timezone' =>
                    $settings['timezone'],
                'next_allowed_at' =>
                    null,
                'next_allowed_utc' =>
                    null,
            ];
        }

        $start =
            (string) $settings[
                'start_time'
            ];

        $end =
            (string) $settings[
                'end_time'
            ];

        $current =
            $localNow->format('H:i');

        $allowed = false;

        if ($start < $end) {
            $allowed =
                $current >= $start
                && $current < $end;
        } else {
            /*
             * Generic overnight window support,
             * for example 22:00 -> 07:00.
             */
            $allowed =
                $current >= $start
                || $current < $end;
        }

        if ($allowed) {
            return [
                'allowed' => true,
                'status' =>
                    'sms_policy_allowed',
                'all_day' => false,
                'start_time' => $start,
                'end_time' => $end,
                'timezone' =>
                    $settings['timezone'],
                'next_allowed_at' =>
                    null,
                'next_allowed_utc' =>
                    null,
            ];
        }

        $next =
            $this->nextAllowed(
                $localNow,
                $start,
                $end
            );

        return [
            'allowed' => false,
            'status' =>
                'sms_window_closed',
            'all_day' => false,
            'start_time' => $start,
            'end_time' => $end,
            'timezone' =>
                $settings['timezone'],
            'next_allowed_at' =>
                $next->format(
                    'Y-m-d\TH:i:sP'
                ),
            'next_allowed_utc' =>
                Clock::databaseTimestamp(
                    $next
                ),
        ];
    }

    public function save(
        array $input
    ): array {
        $allDay =
            $this->inputBoolean(
                $input['all_day']
                ?? null
            );

        $start =
            $this->strictTime(
                (string) (
                    $input['start_time']
                    ?? self::DEFAULT_START
                )
            );

        $end =
            $this->strictTime(
                (string) (
                    $input['end_time']
                    ?? self::DEFAULT_END
                )
            );

        if (
            !$allDay
            && $start === $end
        ) {
            throw new InvalidArgumentException(
                'sms_policy_window_invalid'
            );
        }

        $values = [
            'all_day' =>
                $allDay ? '1' : '0',
            'start_time' => $start,
            'end_time' => $end,
        ];

        $db = Database::connect();

        $statement =
            $db->prepare("
                INSERT INTO app_settings (
                    user_id,
                    namespace,
                    setting_key,
                    setting_value,
                    value_type,
                    is_public,
                    created_at,
                    updated_at
                )
                VALUES (
                    0,
                    ?,
                    ?,
                    ?,
                    'string',
                    0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    setting_value =
                        VALUES(setting_value),
                    value_type =
                        VALUES(value_type),
                    is_public = 0,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $db->beginTransaction();

        try {
            foreach (
                $values
                as $key => $value
            ) {
                $statement->execute([
                    self::NAMESPACE,
                    $key,
                    $value,
                ]);
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        $this->cachedSettings = null;

        return $this->settings();
    }

    public function summary(): string
    {
        $settings =
            $this->settings();

        if (
            (bool) (
                $settings['all_day']
                ?? false
            )
        ) {
            return '24h';
        }

        return
            (string) $settings[
                'start_time'
            ]
            . '-'
            . (string) $settings[
                'end_time'
            ];
    }

    private function nextAllowed(
        DateTimeImmutable $localNow,
        string $start,
        string $end
    ): DateTimeImmutable {
        [$startHour, $startMinute] =
            array_map(
                'intval',
                explode(':', $start)
            );

        $todayStart =
            $localNow->setTime(
                $startHour,
                $startMinute,
                0
            );

        if ($start < $end) {
            if ($localNow < $todayStart) {
                return $todayStart;
            }

            return $todayStart
                ->modify('+1 day');
        }

        /*
         * For an overnight window the only
         * blocked interval is end -> start.
         */
        return $todayStart;
    }

    private function strictTime(
        string $value
    ): string {
        $value = trim($value);

        if (
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/D',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'sms_policy_time_invalid'
            );
        }

        return $value;
    }

    private function normalizeTime(
        mixed $value,
        string $fallback
    ): string {
        $value = trim(
            (string) $value
        );

        if (
            preg_match(
                '/^((?:[01]\d|2[0-3]):[0-5]\d)(?::[0-5]\d)?$/D',
                $value,
                $matches
            ) !== 1
        ) {
            return $fallback;
        }

        return (string) $matches[1];
    }

    private function inputBoolean(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower(
                trim((string) $value)
            ),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }

    private function boolean(
        mixed $value
    ): bool {
        return $this->inputBoolean(
            $value
        );
    }
}
