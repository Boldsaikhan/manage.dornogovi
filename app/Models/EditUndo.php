<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Хэрэглэгчийн сүүлийн үйлдлүүд — «Буцаах» боломжид.
 *
 * Мөр бүр нь өмнөх утгуудыг агуулна. Өгөгдлийн санд хадгалагддаг тул
 * хуудсыг дахин ачаалсан ч буцаах боломж хэвээр байна.
 */
class EditUndo extends Model
{
    /** Хэрэглэгч бүрд хадгалах хамгийн их түүх. */
    public const KEEP = 10;

    protected $fillable = ['user_id', 'model_type', 'model_id', 'label', 'summary', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Өөрчлөгдөх гэж буй утгуудыг бүртгэнэ.
     *
     * @param  array<string, mixed>  $original  Өмнөх утгууд (талбар => утга)
     */
    public static function record(
        ?User $user,
        Model $model,
        array $original,
        string $label,
        ?string $summary = null,
    ): void {
        if (! $user || ! $original) {
            return;
        }

        static::create([
            'user_id' => $user->id,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'label' => $label,
            'summary' => $summary,
            'payload' => $original,
        ]);

        static::trim($user);
    }

    /**
     * Устгахаас өмнө бүтэн мөрийг бүртгэнэ — буцаахад дахин үүсгэнэ.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordDelete(
        ?User $user,
        Model $model,
        array $attributes,
        string $label,
        ?string $summary = null,
    ): void {
        if (! $user || $attributes === []) {
            return;
        }

        static::create([
            'user_id' => $user->id,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'label' => $label,
            'summary' => $summary,
            'payload' => [
                '_deleted' => true,
                'attributes' => $attributes,
            ],
        ]);

        static::trim($user);
    }

    /**
     * Зөвхөн сүүлийн KEEP ширхэгийг үлдээнэ.
     */
    public static function trim(User $user): void
    {
        $keepIds = static::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(self::KEEP)
            ->pluck('id');

        static::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Энэ бүртгэлийг буцааж, өмнөх утгуудыг сэргээнэ.
     */
    public function revert(): bool
    {
        /** @var class-string<Model>|null $class */
        $class = $this->model_type;

        if (! $class || ! class_exists($class)) {
            $this->delete();

            return false;
        }

        $payload = $this->payload ?? [];

        if (($payload['_deleted'] ?? false) === true) {
            $attributes = $payload['attributes'] ?? [];

            if (! is_array($attributes) || $attributes === []) {
                $this->delete();

                return false;
            }

            unset($attributes['id']);
            $class::query()->create($attributes);
            $this->delete();

            return true;
        }

        $model = $class::query()->find($this->model_id);

        if (! $model) {
            $this->delete();

            return false;
        }

        $model->forceFill($payload)->save();
        $this->delete();

        return true;
    }
}
