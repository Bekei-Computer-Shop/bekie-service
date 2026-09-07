<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
        'autoload',
        'user_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'autoload' => 'boolean',
    ];

    public function decodedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }

    public static function putStore(string $key, mixed $value): self
    {
        [$type, $encoded] = match (true) {
            is_bool($value) => ['boolean', $value ? '1' : '0'],
            is_int($value) => ['integer', (string) $value],
            is_float($value) => ['float', (string) $value],
            is_array($value) => ['json', json_encode($value, JSON_THROW_ON_ERROR)],
            default => ['string', (string) $value],
        };

        return static::updateOrCreate(
            ['key' => $key],
            [
                'group' => 'store',
                'value' => $encoded,
                'type' => $type,
                'autoload' => true,
                'is_public' => false,
            ],
        );
    }
}
