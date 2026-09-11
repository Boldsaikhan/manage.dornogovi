<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Өөрчлөлтийн лог — мөр хэзээ, хэнээр, ямар замаар өөрчлөгдсөн.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'created' => 'Шинээр нэмсэн',
        'updated' => 'Засварласан',
        'deleted' => 'Устгасан',
        'imported' => 'Файлаас оруулсан',
        'file_added' => 'PDF хавсаргасан',
        'file_removed' => 'PDF устгасан',
        'restored' => 'Буцаасан',
    ];

    protected $fillable = [
        'user_id', 'model_type', 'model_id', 'action',
        'scope', 'label', 'summary', 'changes', 'source', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Бичлэг үлдээнэ.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function record(
        string $modelType,
        ?int $modelId,
        string $action,
        ?string $scope = null,
        ?string $label = null,
        ?string $summary = null,
        ?array $changes = null,
        ?string $source = null,
    ): void {
        /*
         * Лог бичих нь хэрэглэгчийн ажлыг хэзээ ч зогсоохгүй.
         *
         * Урт бичвэр, Word-оос хуулсан эвдэрсэн тэмдэгт зэргээс болж
         * бичилт бүтэлгүйтвэл өгөгдөл хадгалагдахгүй үлдэх нь буруу —
         * алдааг нь системийн лог руу бичээд цааш үргэлжилнэ.
         */
        try {
            static::query()->create([
                'user_id' => auth()->id(),
                'model_type' => $modelType,
                'model_id' => $modelId,
                'action' => $action,
                'scope' => self::trim($scope, 64),
                'label' => self::trim($label, 240),
                'summary' => self::trim($summary, 2000),
                'changes' => self::cleanChanges($changes),
                'source' => $source ?? (app()->runningInConsole() ? 'console' : 'web'),
                'ip' => app()->runningInConsole() ? null : Request::ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Өөрчлөлтийн лог бичигдсэнгүй: '.$e->getMessage(), [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'action' => $action,
            ]);
        }
    }

    /**
     * Хадгалахад аюулгүй бичвэр болгоно.
     *
     * Буруу UTF-8 байвал JSON болгоход алдаа өгдөг тул цэвэрлэнэ.
     */
    private static function trim(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return mb_substr(trim($clean), 0, $limit);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    private static function cleanChanges(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        $out = [];

        foreach ($changes as $field => $change) {
            $key = (string) self::trim((string) $field, 120);

            $out[$key] = is_array($change)
                ? array_map(fn ($value) => self::clean($value), $change)
                : self::clean($change);
        }

        return $out;
    }

    /** Тоо, логик утгыг хэвээр нь, бичвэрийг л цэвэрлэж богиносгоно. */
    private static function clean(mixed $value): mixed
    {
        return is_string($value) ? self::trim($value, 500) : $value;
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
