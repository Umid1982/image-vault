<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $table = 'images';
    protected $fillable = [
        'user_id',
        'path',
        'original_name',
        'mime',
        'size',
        'hash',
        'conversion_status',
        'converted_at',
        'conversion_failed_at',
        'conversion_skipped_at',
        'conversion_error',
        'conversion_skip_reason',
        'conversion_attempts',
        'conversion_quality',
        'original_size',
        'compression_ratio',
    ];
    protected $dates = [
        'converted_at',
        'conversion_failed_at',
        'conversion_skipped_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope для получения изображений по статусу конвертации
     */
    public function scopeByConversionStatus($query, $status)
    {
        return $query->where('conversion_status', $status);
    }

    /**
     * Scope для получения неконвертированных изображений
     */
    public function scopeNotConverted($query)
    {
        return $query->whereNull('conversion_status')
            ->orWhere('conversion_status', '!=', 'completed');
    }

    /**
     * Проверка, конвертировано ли изображение
     */
    public function isConverted(): bool
    {
        return $this->conversion_status === 'completed';
    }

    /**
     * Проверка, не удалась ли конвертация
     */
    public function hasConversionFailed(): bool
    {
        return in_array($this->conversion_status, ['failed', 'permanently_failed']);
    }
}
