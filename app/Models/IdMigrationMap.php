<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdMigrationMap extends Model
{
    protected $table = 'id_migration_map';

    protected $fillable = [
        'source_system',
        'table_name',
        'source_id',
        'target_id',
        'source_type',
        'target_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'migrated_at' => 'datetime',
        ];
    }

    public static function mapSourceToTarget(string $table, mixed $sourceId): ?string
    {
        return static::where('table_name', $table)
            ->where('source_id', (string) $sourceId)
            ->value('target_id');
    }

    public static function recordMapping(
        string $table,
        mixed $sourceId,
        mixed $targetId,
        string $sourceType = 'integer',
        string $targetType = 'integer',
        ?array $metadata = null
    ): static {
        return static::updateOrCreate(
            [
                'table_name' => $table,
                'source_id' => (string) $sourceId,
            ],
            [
                'target_id' => (string) $targetId,
                'source_type' => $sourceType,
                'target_type' => $targetType,
                'metadata' => $metadata ?? [],
            ]
        );
    }
}
