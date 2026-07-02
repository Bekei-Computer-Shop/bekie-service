<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $metric_key
 * @property float $metric_value
 * @property string $granularity
 * @property Carbon $report_date
 */
class ReportsCache extends Model
{
    use HasFactory;

    protected $table = 'reports_cache';

    protected $fillable = [
        'report_date',
        'metric_key',
        'metric_value',
        'granularity',
    ];

    protected $casts = [
        'report_date' => 'date',
        'metric_value' => 'decimal:4',
    ];

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('report_date', $date);
    }

    public function scopeGranularity(Builder $query, string $granularity): Builder
    {
        return $query->where('granularity', $granularity);
    }

    public static function salesByDateRange(Carbon $start, Carbon $end): Collection
    {
        return self::whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->where('granularity', 'daily')
            ->orderBy('report_date')
            ->get()
            ->groupBy(fn (self $record) => $record->report_date->toDateString())
            ->map(function (Collection $records, string $date): array {
                return [
                    'date' => $date,
                    'orders' => $records->where('metric_key', 'orders')->sum('metric_value'),
                    'revenue' => $records->where('metric_key', 'revenue')->sum('metric_value'),
                    'average_order_value' => $records->where('metric_key', 'average_order_value')->avg('metric_value') ?: 0,
                ];
            })
            ->values();
    }
}
