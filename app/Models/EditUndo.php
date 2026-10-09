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

    /** Буцаах стек. */
    public const UNDO = 'undo';

    /** Дахин хийх стек. */
    public const REDO = 'redo';

    protected $fillable = ['user_id', 'kind', 'model_type', 'model_id', 'label', 'summary', 'payload'];

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
            'kind' => self::UNDO,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'label' => $label,
            'summary' => $summary,
            'payload' => $original,
        ]);

        // Шинэ үйлдэл хийсэн тул «дахин хийх» нь утгаа алдана.
        static::clearRedo($user);
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
            'kind' => self::UNDO,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'label' => $label,
            'summary' => $summary,
            'payload' => [
                '_deleted' => true,
                'attributes' => $attributes,
            ],
        ]);

        static::clearRedo($user);
        static::trim($user);
    }

    /**
     * Стек тус бүрд зөвхөн сүүлийн KEEP ширхэгийг үлдээнэ.
     */
    public static function trim(User $user, string $kind = self::UNDO): void
    {
        $keepIds = static::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->orderByDesc('id')
            ->limit(self::KEEP)
            ->pluck('id');

        static::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /** «Дахин хийх» стекийг хоослоно. */
    public static function clearRedo(User $user): void
    {
        static::query()
            ->where('user_id', $user->id)
            ->where('kind', self::REDO)
            ->delete();
    }

    /** Тухайн стекийн хамгийн сүүлийн бүртгэл. */
    public static function latestFor(User $user, string $kind): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->orderByDesc('id')
            ->first();
    }

    /** Эсрэг стекийн нэр. */
    public function oppositeKind(): string
    {
        return $this->kind === self::REDO ? self::UNDO : self::REDO;
    }

    /**
     * Энэ бүртгэлийг буцааж, өмнөх утгуудыг сэргээнэ.
     *
     * Буцаахын өмнө одоогийн байдлыг эсрэг стект бичнэ — ингэснээр
     * «буцаах»-ыг дахин хийж, «дахин хийх»-ийг буцааж болно.
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

        // Устгасан мөрийг сэргээнэ — эсрэг нь дахин устгах.
        if (($payload['_deleted'] ?? false) === true) {
            $attributes = $payload['attributes'] ?? [];

            if (! is_array($attributes) || $attributes === []) {
                $this->delete();

                return false;
            }

            unset($attributes['id']);
            $restored = $class::query()->create($attributes);

            $this->storeOpposite($restored->getKey(), ['_delete' => true]);
            $this->delete();

            return true;
        }

        $model = $class::query()->find($this->model_id);

        if (! $model) {
            $this->delete();

            return false;
        }

        // Мөрийг устгана — эсрэг нь дахин сэргээх.
        if (($payload['_delete'] ?? false) === true) {
            $this->storeOpposite($model->getKey(), [
                '_deleted' => true,
                'attributes' => $model->getAttributes(),
            ]);

            $model->delete();
            $this->delete();

            return true;
        }

        // Талбарын утгууд — эсрэг нь одоогийн утгууд.
        $current = [];

        foreach (array_keys($payload) as $field) {
            $current[$field] = $model->getAttribute($field);
        }

        $this->storeOpposite($model->getKey(), $current);

        $model->forceFill($payload)->save();
        $this->delete();

        return true;
    }

    /**
     * Эсрэг стект бичнэ.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeOpposite(mixed $modelId, array $payload): void
    {
        $user = $this->user;

        if (! $user) {
            return;
        }

        $kind = $this->oppositeKind();

        static::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'model_type' => $this->model_type,
            'model_id' => $modelId,
            'label' => $this->label,
            'summary' => $this->summary,
            'payload' => $payload,
        ]);

        static::trim($user, $kind);
    }
}
