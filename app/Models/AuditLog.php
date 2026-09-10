<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        static::query()->create([
            'user_id' => auth()->id(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'scope' => $scope,
            'label' => $label,
            'summary' => $summary,
            'changes' => $changes,
            'source' => $source ?? (app()->runningInConsole() ? 'console' : 'web'),
            'ip' => app()->runningInConsole() ? null : Request::ip(),
            'created_at' => now(),
        ]);
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
