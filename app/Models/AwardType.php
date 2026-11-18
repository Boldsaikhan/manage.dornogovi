<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AwardType extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Шагналын бүртгэлд ашиглагдаж байгаа эсэх (устгахыг хориглоход). */
    public function isInUse(): bool
    {
        return Award::query()
            ->where(function ($query) {
                $query->where('nominated_award', $this->name)
                    ->orWhere('award_name', $this->name)
                    ->orWhere('last_award', $this->name)
                    ->orWhere('received_award', $this->name);
            })
            ->exists();
    }

    /**
     * @return Collection<string, true>
     */
    public static function usedNames(): Collection
    {
        $names = static::query()->pluck('name');

        if ($names->isEmpty()) {
            return collect();
        }

        return Award::query()
            ->where(function ($query) use ($names) {
                $query->whereIn('nominated_award', $names)
                    ->orWhereIn('award_name', $names)
                    ->orWhereIn('last_award', $names)
                    ->orWhereIn('received_award', $names);
            })
            ->get(['nominated_award', 'award_name', 'last_award', 'received_award'])
            ->flatMap(fn (Award $award) => [
                $award->nominated_award,
                $award->award_name,
                $award->last_award,
                $award->received_award,
            ])
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $name) => [$name => true]);
    }

    /**
     * @return list<array{id: int, value: string, label: string}>
     */
    public static function options(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (self $type) => [
                'id' => $type->id,
                'value' => $type->name,
                'label' => $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Жагсаалтын дарааллаар ($anchorName-аас доош) бүртгэгдсэн нэрүүдийг устгана.
     *
     * @return int Устгасан мөрийн тоо
     */
    public static function deleteAfter(string $anchorName): int
    {
        $rows = static::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $passedAnchor = false;
        $ids = [];

        foreach ($rows as $row) {
            if (! $passedAnchor) {
                if ($row->name === $anchorName) {
                    $passedAnchor = true;
                }

                continue;
            }

            $ids[] = (int) $row->id;
        }

        if ($ids === []) {
            return 0;
        }

        return static::query()->whereIn('id', $ids)->delete();
    }

    /** @return list<string> */
    public static function defaultNames(): array
    {
        return [
            'Монгол Улсын Баатар цол',
            'Монгол Улсын Хөдөлмөрийн баатар цол',
            'Ардын багш цол',
            'Ардын жүжигчин цол',
            'Ардын зураач цол',
            'Ардын уран зохиолч цол',
            'Ардын эмч цол',
            'Аж үйлдвэрийн гавьяат ажилтан',
            'Байгаль орчны гавьяат ажилтан',
            'Гавьяат агрономич',
            'Гавьяат барилгачин',
            'Гавьяат жүжигчин',
            'Гавьяат мал зүйч',
            'Гавьяат малчин',
            'Гавьяат тамирчин',
            'Гавьяат тээвэрчин',
            'Гавьяат усжуулагч',
            'Гавьяат уурхайчин',
            'Гавьяат холбоочин',
            'Гавьяат хуульч',
            'Малын гавьяат эмч',
            'Ойн гавьяат ажилтан',
            'Соёлын гавьяат зүтгэлтэн',
            'Үйлчилгээний гавьяат ажилтан',
            'Урлагийн гавьяат зүтгэлтэн',
            'Ус цаг уурын гавьяат ажилтан',
            'Хөдөө аж ахуйн гавьяат ажилтан',
            'Худалдааны гавьяат ажилтан',
            'Хүний гавьяат эмч',
            'Шинжлэх ухааны гавьяат зүтгэлтэн',
            'Эрүүл мэндийг хамгаалах гавьяат ажилтан',
            'Сүхбаатарын одон',
            'Хөдөлмөрийн гавьяаны улаан тугийн одон',
            'Цэргийн гавьяаны улаан тугийн одон',
            'Хөдөлмөрийн хүндэт медаль',
            'Цэргийн хүндэт медаль',
            'Шударга журам медаль',
            'Найрамдал медаль',
        ];
    }
}
