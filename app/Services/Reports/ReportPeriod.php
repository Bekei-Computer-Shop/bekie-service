<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The date window and bucket size shared by the admin sales and customer-order
 * reports.
 *
 * Timestamps are stored in UTC but a report is read in shop time, so the window
 * is resolved in `app.report_timezone` and converted to UTC only for the query.
 * Every bucket in the window is listed up front, which is what lets a report
 * show a quiet day or month as zero instead of dropping it from the chart.
 */
final class ReportPeriod
{
    public const HOUR = 'hour';

    public const DAY = 'day';

    public const WEEK = 'week';

    public const MONTH = 'month';

    public const YEAR = 'year';

    /** Finest to coarsest; a window with too many buckets steps up this list. */
    private const UNITS = [self::HOUR, self::DAY, self::WEEK, self::MONTH, self::YEAR];

    /** Past this the chart is unreadable and the table is a wall of rows. */
    private const MAX_BUCKETS = 370;

    private function __construct(
        public readonly string $preset,
        public readonly string $unit,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $timezone,
    ) {}

    /**
     * @param  array{preset?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  bool  $hourly  Sales splits a single day by hour. Customer orders have
     *                        no use for that, so a single day stays one daily bucket
     *                        and "daily" defaults to the last seven days instead.
     */
    public static function resolve(array $filters, bool $hourly = true): self
    {
        $timezone = (string) config('app.report_timezone');
        $preset = (string) ($filters['preset'] ?? 'custom');

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $start = CarbonImmutable::parse($filters['date_from'], $timezone)->setTimezone($timezone)->startOfDay();
            $end = CarbonImmutable::parse($filters['date_to'], $timezone)->setTimezone($timezone)->endOfDay();
        } else {
            [$start, $end] = self::defaultWindow($preset, Carbon::now($timezone)->toImmutable(), $hourly);
        }

        $days = self::daysBetween($start, $end);
        $unit = match ($preset) {
            'daily' => $hourly && $days === 1 ? self::HOUR : self::DAY,
            'weekly' => self::WEEK,
            'monthly' => self::MONTH,
            'yearly' => self::YEAR,
            default => self::DAY,
        };

        while ($unit !== self::YEAR && self::estimateBuckets($unit, $start, $end, $days) > self::MAX_BUCKETS) {
            $unit = self::UNITS[array_search($unit, self::UNITS, true) + 1];
        }

        return new self($preset, $unit, $start, $end, $timezone);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function defaultWindow(string $preset, CarbonImmutable $now, bool $hourly): array
    {
        return match ($preset) {
            'daily' => [$hourly ? $now->startOfDay() : $now->subDays(6)->startOfDay(), $now->endOfDay()],
            'weekly' => [
                $now->startOfWeek(CarbonInterface::MONDAY)->subWeeks(9),
                $now->endOfWeek(CarbonInterface::SUNDAY),
            ],
            'monthly' => [$now->startOfMonth()->subMonths(11), $now->endOfMonth()],
            'yearly' => [$now->startOfYear()->subYears(4), $now->endOfYear()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    /** Whole calendar days covered, counting both ends. Rounded so a DST shift can't skew it. */
    private static function daysBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) round(($end->startOfDay()->getTimestamp() - $start->startOfDay()->getTimestamp()) / 86400) + 1;
    }

    private static function estimateBuckets(string $unit, CarbonImmutable $start, CarbonImmutable $end, int $days): int
    {
        return match ($unit) {
            self::HOUR => 24,
            self::DAY => $days,
            self::WEEK => intdiv($days, 7) + 2,
            self::MONTH => (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1,
            default => $end->year - $start->year + 1,
        };
    }

    public function days(): int
    {
        return self::daysBetween($this->start, $this->end);
    }

    /** The window of equal length that ends the day before this one starts. */
    public function previous(): self
    {
        $end = $this->start->subDay()->endOfDay();
        $start = $end->subDays($this->days() - 1)->startOfDay();

        return new self($this->preset, $this->unit, $start, $end, $this->timezone);
    }

    /** Inclusive lower bound for a `created_at >= ?` filter, in the database's UTC. */
    public function startUtc(): string
    {
        return $this->start->utc()->toDateTimeString();
    }

    /** Exclusive upper bound, so a row stamped 23:59:59.5 still lands in the last day. */
    public function endExclusiveUtc(): string
    {
        return $this->end->startOfDay()->addDay()->utc()->toDateTimeString();
    }

    /** A stored UTC timestamp, read as the shop would. */
    public function localize(string $utc): CarbonImmutable
    {
        return CarbonImmutable::parse($utc, (string) config('app.timezone'))->setTimezone($this->timezone);
    }

    /** Identifies the bucket a local time falls in; matches the `key` of {@see buckets()}. */
    public function keyFor(CarbonInterface $local): string
    {
        return match ($this->unit) {
            self::HOUR => $local->format('Y-m-d H'),
            self::DAY => $local->format('Y-m-d'),
            self::WEEK => $local->format('o-\WW'),
            self::MONTH => $local->format('Y-m'),
            default => $local->format('Y'),
        };
    }

    /**
     * Every bucket in the window, in order, empty or not.
     *
     * `label` is the short axis text, `title` the longer row heading, and
     * `date_range` the days the bucket covers once clipped to the window (the
     * first and last week or month are usually partial).
     *
     * @return list<array{key: string, label: string, title: string, date_range: string}>
     */
    public function buckets(): array
    {
        $buckets = [];
        $crossesYear = $this->start->year !== $this->end->year;
        $cursor = $this->bucketStart($this->start);

        while ($cursor <= $this->end) {
            $next = $this->advance($cursor);
            $from = $cursor < $this->start ? $this->start : $cursor;
            $lastMoment = $next->subSecond();
            $to = $lastMoment > $this->end ? $this->end : $lastMoment;

            $buckets[] = [
                'key' => $this->keyFor($cursor),
                'label' => $this->label($cursor, $crossesYear),
                'title' => $this->title($cursor, $lastMoment, $crossesYear),
                'date_range' => in_array($this->unit, [self::HOUR, self::DAY], true)
                    ? $from->toDateString()
                    : $from->toDateString().' - '.$to->toDateString(),
            ];

            $cursor = $next;
        }

        return $buckets;
    }

    private function bucketStart(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this->unit) {
            self::HOUR => $moment->startOfHour(),
            self::DAY => $moment->startOfDay(),
            self::WEEK => $moment->startOfWeek(CarbonInterface::MONDAY),
            self::MONTH => $moment->startOfMonth(),
            default => $moment->startOfYear(),
        };
    }

    private function advance(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this->unit) {
            self::HOUR => $bucketStart->addHour(),
            self::DAY => $bucketStart->addDay(),
            self::WEEK => $bucketStart->addWeek(),
            self::MONTH => $bucketStart->addMonth(),
            default => $bucketStart->addYear(),
        };
    }

    /** Short text for a chart axis. The year is added only when the window crosses one. */
    private function label(CarbonImmutable $bucketStart, bool $crossesYear): string
    {
        $year = $crossesYear ? " '".$bucketStart->format('y') : '';

        return match ($this->unit) {
            self::HOUR => $bucketStart->format('H:00'),
            self::DAY => $bucketStart->format('M j').$year,
            self::WEEK => 'W'.$bucketStart->isoWeek().($crossesYear ? " '".substr($bucketStart->format('o'), -2) : ''),
            self::MONTH => $bucketStart->format('M').$year,
            default => $bucketStart->format('Y'),
        };
    }

    /** Longer heading for a table row or tooltip. */
    private function title(CarbonImmutable $bucketStart, CarbonImmutable $bucketEnd, bool $crossesYear): string
    {
        return match ($this->unit) {
            self::HOUR => $bucketStart->format('H:00'),
            self::DAY => $bucketStart->format($crossesYear ? 'M j, Y' : 'M j'),
            self::WEEK => $bucketStart->format('M j').' – '.$bucketEnd->format($crossesYear ? 'M j, Y' : 'M j'),
            self::MONTH => $bucketStart->format('M Y'),
            default => $bucketStart->format('Y'),
        };
    }

    /** @return array<string, string|int> */
    public function meta(): array
    {
        $previous = $this->previous();

        return [
            'preset' => $this->preset,
            'unit' => $this->unit,
            'timezone' => $this->timezone,
            'date_from' => $this->start->toDateString(),
            'date_to' => $this->end->toDateString(),
            'previous_date_from' => $previous->start->toDateString(),
            'previous_date_to' => $previous->end->toDateString(),
        ];
    }
}
