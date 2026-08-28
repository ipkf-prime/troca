<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class BusinessCalendarService
{
    public function addBusinessMinutes(
        DateTimeImmutable $startUtc,
        int $minutes,
        array $calendar
    ): DateTimeImmutable {
        if ($minutes < 0) {
            throw new RuntimeException(
                'ticketing_sla_minutes_invalid'
            );
        }


        if ($minutes === 0) {
            return
                $startUtc->setTimezone(
                    new DateTimeZone('UTC')
                );
        }


        $timezone =
            $this->timezone(
                (string) (
                    $calendar['timezone']
                    ?? 'Asia/Tehran'
                )
            );


        $hours =
            $this->hoursByWeekday(
                is_array(
                    $calendar['hours']
                    ?? null
                )
                    ? $calendar['hours']
                    : []
            );


        $exceptions =
            $this->exceptionsByDate(
                is_array(
                    $calendar['exceptions']
                    ?? null
                )
                    ? $calendar['exceptions']
                    : []
            );


        $cursor =
            $startUtc->setTimezone(
                $timezone
            );


        $remainingSeconds =
            $minutes * 60;


        for ($dayGuard = 0;
            $dayGuard < 5000;
            $dayGuard++
        ) {
            $date =
                $cursor->format('Y-m-d');


            $intervals =
                $this->intervalsForDate(
                    $date,
                    $timezone,
                    $hours,
                    $exceptions
                );


            foreach ($intervals as $interval) {

                $from =
                    $interval[0];

                $to =
                    $interval[1];


                if ($cursor >= $to) {
                    continue;
                }


                if ($cursor < $from) {
                    $cursor = $from;
                }


                $available =
                    $to->getTimestamp()
                    - $cursor->getTimestamp();


                if ($available <= 0) {
                    continue;
                }


                if (
                    $remainingSeconds
                    <= $available
                ) {
                    $cursor =
                        $cursor->modify(
                            '+'
                            . $remainingSeconds
                            . ' seconds'
                        );


                    return
                        $cursor->setTimezone(
                            new DateTimeZone(
                                'UTC'
                            )
                        );
                }


                $remainingSeconds -=
                    $available;

                $cursor = $to;
            }


            $cursor =
                $cursor
                    ->modify('+1 day')
                    ->setTime(0, 0, 0);
        }


        throw new RuntimeException(
            'ticketing_sla_calendar_guard_exhausted'
        );
    }


    public function businessMinutesBetween(
        DateTimeImmutable $fromUtc,
        DateTimeImmutable $toUtc,
        array $calendar
    ): int {
        if ($toUtc <= $fromUtc) {
            return 0;
        }


        $timezone =
            $this->timezone(
                (string) (
                    $calendar['timezone']
                    ?? 'Asia/Tehran'
                )
            );


        $hours =
            $this->hoursByWeekday(
                is_array(
                    $calendar['hours']
                    ?? null
                )
                    ? $calendar['hours']
                    : []
            );


        $exceptions =
            $this->exceptionsByDate(
                is_array(
                    $calendar['exceptions']
                    ?? null
                )
                    ? $calendar['exceptions']
                    : []
            );


        $from =
            $fromUtc->setTimezone(
                $timezone
            );

        $to =
            $toUtc->setTimezone(
                $timezone
            );


        $cursor =
            $from->setTime(
                0,
                0,
                0
            );


        $seconds = 0;


        for ($guard = 0;
            $guard < 5000
            && $cursor < $to;
            $guard++
        ) {
            $date =
                $cursor->format(
                    'Y-m-d'
                );


            foreach (
                $this->intervalsForDate(
                    $date,
                    $timezone,
                    $hours,
                    $exceptions
                )
                as $interval
            ) {
                $start =
                    $interval[0] > $from
                        ? $interval[0]
                        : $from;

                $end =
                    $interval[1] < $to
                        ? $interval[1]
                        : $to;


                if ($end > $start) {
                    $seconds +=
                        $end->getTimestamp()
                        - $start->getTimestamp();
                }
            }


            $cursor =
                $cursor
                    ->modify('+1 day')
                    ->setTime(0, 0, 0);
        }


        return
            (int) floor(
                $seconds / 60
            );
    }


    private function intervalsForDate(
        string $date,
        DateTimeZone $timezone,
        array $hours,
        array $exceptions
    ): array {
        $exceptionRows =
            $exceptions[$date]
            ?? [];


        if ($exceptionRows !== []) {

            foreach ($exceptionRows as $row) {

                if (
                    (string) (
                        $row[
                            'exception_type_code'
                        ]
                        ?? ''
                    )
                    === 'holiday'
                ) {
                    return [];
                }
            }


            $working = [];

            foreach ($exceptionRows as $row) {

                if (
                    (string) (
                        $row[
                            'exception_type_code'
                        ]
                        ?? ''
                    )
                    !== 'working'
                ) {
                    continue;
                }


                $interval =
                    $this->interval(
                        $date,
                        (string) (
                            $row[
                                'start_time'
                            ]
                            ?? ''
                        ),
                        (string) (
                            $row[
                                'end_time'
                            ]
                            ?? ''
                        ),
                        $timezone
                    );


                if ($interval !== null) {
                    $working[] =
                        $interval;
                }
            }


            return
                $this->sortIntervals(
                    $working
                );
        }


        $probe =
            new DateTimeImmutable(
                $date . ' 12:00:00',
                $timezone
            );


        $weekday =
            (int) $probe->format('N');


        $normal = [];

        foreach (
            $hours[$weekday]
            ?? []
            as $row
        ) {
            if (
                empty(
                    $row['is_working']
                )
            ) {
                continue;
            }


            $interval =
                $this->interval(
                    $date,
                    (string) (
                        $row['start_time']
                        ?? ''
                    ),
                    (string) (
                        $row['end_time']
                        ?? ''
                    ),
                    $timezone
                );


            if ($interval !== null) {
                $normal[] =
                    $interval;
            }
        }


        return
            $this->sortIntervals(
                $normal
            );
    }


    private function interval(
        string $date,
        string $start,
        string $end,
        DateTimeZone $timezone
    ): ?array {
        $start =
            trim($start);

        $end =
            trim($end);


        if (
            $start === ''
            || $end === ''
        ) {
            return null;
        }


        $from =
            new DateTimeImmutable(
                $date . ' ' . $start,
                $timezone
            );

        $to =
            new DateTimeImmutable(
                $date . ' ' . $end,
                $timezone
            );


        if ($to <= $from) {
            return null;
        }


        return [
            $from,
            $to,
        ];
    }


    private function hoursByWeekday(
        array $rows
    ): array {
        $result = [];


        foreach ($rows as $row) {

            $weekday =
                (int) (
                    $row['weekday_iso']
                    ?? 0
                );


            if (
                $weekday < 1
                || $weekday > 7
            ) {
                continue;
            }


            $result[$weekday][] =
                $row;
        }


        return $result;
    }


    private function exceptionsByDate(
        array $rows
    ): array {
        $result = [];


        foreach ($rows as $row) {

            $date =
                trim(
                    (string) (
                        $row[
                            'exception_date'
                        ]
                        ?? ''
                    )
                );


            if ($date === '') {
                continue;
            }


            $result[$date][] =
                $row;
        }


        return $result;
    }


    private function sortIntervals(
        array $intervals
    ): array {
        usort(
            $intervals,
            static fn (
                array $left,
                array $right
            ): int =>
                $left[0]
                    <=> $right[0]
        );


        return $intervals;
    }


    private function timezone(
        string $name
    ): DateTimeZone {
        $name =
            trim($name);


        if ($name === '') {
            $name =
                'Asia/Tehran';
        }


        try {
            return
                new DateTimeZone(
                    $name
                );
        } catch (\Throwable) {
            throw new RuntimeException(
                'ticketing_sla_timezone_invalid'
            );
        }
    }
}
