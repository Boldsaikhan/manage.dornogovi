<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskSource extends Model
{
    public const KEY_DIRECTIVE = 'directive';

    public const KEY_PREP_PLAN = 'prep_plan';

    public const LAYOUTS = [self::KEY_DIRECTIVE, self::KEY_PREP_PLAN];

    /**
     * Удирдлагаас өгсөн үүрэг даалгавар — албан тушаал тус бүрээр.
     *
     * Утасны жагсаалтаас тухайн албан тушаалтны нэрийг олж харуулна.
     *
     * @var array<string, string>
     */
    public const LEADERSHIP = [
        'governor' => 'Аймгийн Засаг дарга',
        'vice_governor' => 'Аймгийн Засаг даргын орлогч',
        'chief_of_staff' => 'Аймгийн Засаг даргын Тамгын газрын дарга',
    ];

    /** Хэлтсийн үүрэг даалгаврын байнгын хэсэг. */
    public const KEY_DEPARTMENT = 'department_tasks';

    public const DEPARTMENT_NAME = 'Хэлтсийн үүрэг даалгавар';

    /**
     * Дотоодын үүрэг даалгавар өгдөг нэгжүүд — «Хэлтсийн үүрэг даалгавар»
     * хэсэгт нэг нэгээр нь гарна. Тухайн нэгжийн албан хаагчид л хармаар.
     *
     * @var array<string, string>
     */
    public const UNITS = [
        'unit_01' => 'Төрийн захиргааны удирдлагын хэлтэс',
        'unit_02' => 'Хэвлэл мэдээллийн алба',
        'unit_03' => 'Хууль, эрх зүйн хэлтэс',
        'unit_04' => 'Архивын тасаг',
        'unit_05' => 'Салбарын хяналт, зохицуулалтын хэлтэс',
        'unit_06' => 'Бүс, орон нутгийн хөрөнгө оруулалт, бодлого төлөвлөлтийн хэлтэс',
        'unit_07' => 'Санхүү, төрийн сангийн хэлтэс',
        'unit_08' => 'Нийгмийн бодлогын хэлтэс',
        'unit_09' => 'Хяналт-шинжилгээ, үнэлгээ, дотоод аудитын хэлтэс',
        'unit_10' => 'Цэргийн штаб',
        'unit_11' => 'Санхүү, аж ахуй',
        'unit_12' => 'Теле камерын хяналтын дэд төв',
    ];

    public const COLUMN_SECTOR = 'sector';

    public const COLUMN_MEASURE = 'measure';

    public const COLUMN_TEXT = 'text';

    public const COLUMN_PERIOD = 'period';

    public const COLUMN_RESPONSIBLE = 'responsible';

    public const COLUMN_COLLABORATOR = 'collaborator';

    public const COLUMN_NOTE = 'note';

    protected $fillable = ['key', 'name', 'period', 'sort_order', 'layout', 'columns', 'department_id', 'unit_name'];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TaskDocument::class)->latest();
    }

    public function isSystem(): bool
    {
        return in_array($this->key, [self::KEY_DIRECTIVE, self::KEY_PREP_PLAN], true);
    }

    /**
     * Удирдлагын хэсгийг устгах, нэрийг нь солихыг хориглоно.
     */
    public function isLocked(): bool
    {
        return $this->isLeadership()
            || $this->key === self::KEY_DEPARTMENT
            || $this->isUnit();
    }

    /**
     * Тодорхой нэгжийн дотоодын үүрэг даалгаврын хэсэг эсэх.
     */
    public function isUnit(): bool
    {
        return array_key_exists((string) $this->key, self::UNITS);
    }

    /**
     * Нэгжийн нэрийг харьцуулахад тохируулна — таслал, зураас, эхний
     * дугаарлалтыг («7. Цэргийн штаб») үл тооно.
     */
    public static function normalizeUnitName(?string $name): string
    {
        $name = (string) $name;
        $name = preg_replace('/^\s*\d+[.)]\s*/u', '', $name) ?? $name;
        $name = preg_replace('/[\s,.\-–—]+/u', ' ', $name) ?? $name;

        return trim(mb_strtolower($name));
    }

    /**
     * Энэ хэсгийг тухайн хэрэглэгч харах эрхтэй эсэх (нэгжийн хэсгийн хувьд).
     */
    public function isVisibleToUnitMember(?User $user): bool
    {
        if (! $this->isUnit()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($this->department_id && (int) $user->department_id === (int) $this->department_id) {
            return true;
        }

        $mine = self::normalizeUnitName($user->department?->name);

        return $mine !== '' && $mine === self::normalizeUnitName($this->unit_name ?: $this->name);
    }

    /**
     * Энэ нь тухайн хэрэглэгчийн ХАРЬЯА нэгжийн дотоод хэсэг мөн эсэх.
     *
     * Ийм хэсгийн үүрэг даалгаврыг нэгжийн бүх албан хаагч бүтнээр нь
     * харна — «зөвхөн өөрийн үүрэг» эрх энд хамаарахгүй.
     */
    public function isUnitMemberSection(?User $user): bool
    {
        return $this->isUnit() && $this->isVisibleToUnitMember($user);
    }

    public function isLeadership(): bool
    {
        return array_key_exists((string) $this->key, self::LEADERSHIP);
    }

    /**
     * Удирдлагын хэсэгт тухайн албан тушаалыг хашиж буй хүний нэр.
     */
    public function leaderName(): ?string
    {
        return $this->isLeadership()
            ? PhoneDirectoryEntry::holderFor($this->name)
            : null;
    }

    public function isPrepLayout(): bool
    {
        $layout = $this->layout ?: $this->key;

        return $layout === self::KEY_PREP_PLAN;
    }

    /**
     * Хэсэг нэмэхэд сонгох боломжтой хүснэгтийн толгой.
     *
     * @return list<array{key: string, label: string, field: string, type: string, width: int}>
     */
    public static function columnCatalog(): array
    {
        return [
            ['key' => self::COLUMN_SECTOR, 'label' => 'Ажлын чиглэл', 'field' => 'sector', 'type' => 'text', 'width' => 140],
            ['key' => self::COLUMN_MEASURE, 'label' => 'Арга хэмжээ', 'field' => 'measure', 'type' => 'multiline', 'width' => 280],
            ['key' => self::COLUMN_TEXT, 'label' => 'Үүрэг чиглэл', 'field' => 'text', 'type' => 'multiline', 'width' => 320],
            ['key' => self::COLUMN_PERIOD, 'label' => 'Хугацаа', 'field' => 'period', 'type' => 'period', 'width' => 120],
            ['key' => self::COLUMN_RESPONSIBLE, 'label' => 'Хариуцах эзэн', 'field' => 'responsible', 'type' => 'people', 'width' => 180],
            ['key' => self::COLUMN_COLLABORATOR, 'label' => 'Хяналт тавих', 'field' => 'collaborator', 'type' => 'people', 'width' => 200],
            ['key' => self::COLUMN_NOTE, 'label' => 'Хэрэгжилт', 'field' => 'note', 'type' => 'multiline', 'width' => 160],
        ];
    }

    /**
     * @return list<string>
     */
    public static function columnKeys(): array
    {
        return array_column(self::columnCatalog(), 'key');
    }

    /**
     * @param  list<string>|null  $keys
     * @return list<string>
     */
    public static function normalizeColumnKeys(?array $keys): array
    {
        if (! is_array($keys)) {
            return [];
        }

        $allowed = self::columnKeys();
        $picked = [];

        // Хэрэглэгчийн сонгосон дарааллыг хэвээр нь үлдээнэ — багана яг
        // тэр дарааллаараа гарна.
        foreach ($keys as $key) {
            if (is_string($key)
                && in_array($key, $allowed, true)
                && ! in_array($key, $picked, true)) {
                $picked[] = $key;
            }
        }

        return $picked;
    }

    /**
     * @param  list<string>  $keys
     */
    public static function layoutForColumns(array $keys): string
    {
        if (in_array(self::COLUMN_SECTOR, $keys, true) && ! in_array(self::COLUMN_TEXT, $keys, true)) {
            return self::KEY_PREP_PLAN;
        }

        return self::KEY_DIRECTIVE;
    }

    /**
     * @return list<string>
     */
    public function columnKeyList(): array
    {
        $stored = self::normalizeColumnKeys(is_array($this->columns) ? $this->columns : null);

        if ($stored !== []) {
            return $stored;
        }

        return $this->isPrepLayout()
            ? [self::COLUMN_SECTOR, self::COLUMN_TEXT, self::COLUMN_PERIOD, self::COLUMN_RESPONSIBLE, self::COLUMN_COLLABORATOR, self::COLUMN_NOTE]
            : [self::COLUMN_TEXT, self::COLUMN_PERIOD, self::COLUMN_RESPONSIBLE, self::COLUMN_COLLABORATOR, self::COLUMN_NOTE];
    }

    /**
     * @return list<array{key: string, label: string, field: string, type: string, width: int}>
     */
    public function resolvedColumns(): array
    {
        $catalog = [];
        foreach (self::columnCatalog() as $column) {
            $catalog[$column['key']] = $column;
        }

        $custom = self::normalizeColumnKeys(is_array($this->columns) ? $this->columns : null) !== [];
        $out = [];

        foreach ($this->columnKeyList() as $key) {
            if (! isset($catalog[$key])) {
                continue;
            }

            $column = $catalog[$key];

            if (! $custom && $this->isPrepLayout()) {
                if ($key === self::COLUMN_TEXT) {
                    $column['label'] = 'Арга хэмжээ';
                }
                if ($key === self::COLUMN_COLLABORATOR) {
                    $column['label'] = 'Хамтран хэрэгжүүлэх';
                }
            } elseif (! $custom && $key === self::COLUMN_COLLABORATOR) {
                $column['label'] = 'Хяналт тавих албан тушаалтан';
            }

            $out[] = $column;
        }

        return $out;
    }

    public static function keyFor(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'section';
        $base = substr($base, 0, 24);
        $key = $base;
        $i = 2;

        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
